<?php

declare(strict_types=1);

namespace MyBB\Console\Command\View;

use Exception;
use InvalidArgumentException;
use LogicException;
use MyBB;
use MyBB\Console\Style;
use MyBB\Database\Models\Theme as ThemeModel;
use MyBB\Database\Repositories\ThemeRepository as ThemeModelRepository;
use MyBB\Extensions\Contracts\HierarchicalExtensionInterface;
use MyBB\Extensions\Contracts\ViewExtensionInterface;
use MyBB\Extensions\Extension;
use MyBB\Extensions\Plugin\Plugin;
use MyBB\Extensions\Plugin\Repository as PluginRepository;
use MyBB\Extensions\RepositoryRegistry as ExtensionRepositoryRegistry;
use MyBB\Extensions\Theme\Repository as ThemeRepository;
use MyBB\Extensions\Theme\Theme;
use MyBB\Extensions\Theme\ThemeType;
use MyBB\Maintenance\InstallationState;
use MyBB\Twig\Inspection\FunctionExpressionInspection;
use MyBB\Twig\Inspection\Inspector;
use MyBB\Twig\ViewletLoader;
use MyBB\View\Asset\Asset;
use MyBB\View\Asset\Publication;
use MyBB\View\Asset\StaticAsset;
use MyBB\View\Asset\ViewletAsset;
use MyBB\View\Locator\Exception as LocatorException;
use MyBB\View\Locator\Locator;
use MyBB\View\Locator\StaticLocator;
use MyBB\View\Locator\ViewletLocator;
use MyBB\View\Optimization;
use MyBB\View\Resource;
use MyBB\View\ResourceType;
use MyBB\View\Viewlet\Decorator\CompositeViewlet;
use MyBB\View\Viewlet\Decorator\Hierarchy\HierarchicalViewlet;
use MyBB\View\Viewlet\Decorator\PublishableViewlet;
use MyBB\View\Viewlet\Decorator\ViewletDecorator;
use MyBB\View\Viewlet\NamespaceType;
use MyBB\View\Viewlet\ViewletInterface;
use MyLanguage;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use UnexpectedValueException;

use function MyBB\app;

use const MyBB\View\DEFAULT_THEME_PACKAGE;

#[AsCommand(
    name: 'view:inspect',
    description: 'Displays information about Resources and Assets in given active Theme or Package',
)]
class ViewInspectCommand extends Command
{
    private readonly InputInterface $input;
    private readonly OutputInterface $output;
    private readonly Style $io;

    private readonly ThemeModelRepository $themeModelRepository;


    // resolution context

    private readonly ThemeModel $themeModel;
    private readonly Extension&ViewExtensionInterface $extension;

    private readonly ViewletInterface $viewlet;

    /**
     * @var list<string>
     */
    private readonly array $namespaces;


    public function __construct(
        private readonly MyBB $mybb,
        private readonly MyLanguage $lang,
        private readonly Optimization $optimization,
        private readonly ExtensionRepositoryRegistry $extensionRepositoryRegistry,
        private readonly ThemeRepository $themeRepository,
        private readonly PluginRepository $pluginRepository,
    ) {
        parent::__construct();

        if (InstallationState::get() === InstallationState::INSTALLED) {
            $this->themeModelRepository = app(ThemeModelRepository::class);
        }
    }

    protected function configure(): void
    {
        $this->addOption(
            name: 'resource',
            mode: InputOption::VALUE_REQUIRED,
            description: 'Resource to inspect (Locator, absolute path, or relative path)',
        );
        $this->addOption(
            name: 'asset',
            mode: InputOption::VALUE_REQUIRED,
            description: 'Asset to inspect (Locator, absolute path, relative path, or URL)',
        );

        $this->addOption(
            name: 'package',
            mode: InputOption::VALUE_REQUIRED,
            description: 'Package name to use as resolution context',
            suggestedValues: $this->getExtensionPackageNames(...),
        );
        $this->addOption(
            name: 'theme',
            mode: InputOption::VALUE_REQUIRED,
            description: 'ID of an active Theme to use as resolution context',
            suggestedValues: $this->getThemeIds(...),
        );
        $this->addOption(
            name: 'namespace',
            mode: InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            description: 'Namespaces to use as resolution context',
        );
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;
        $this->io = new Style($input, $output);

        $this->lang->load('global');

        if (InstallationState::get() !== InstallationState::INSTALLED) {
            $this->io->error(
                sprintf(
                    'This command must be used with an installed application (current state: %s)',
                    strip_tags(InstallationState::getDescription($this->lang)),
                ),
            );

            return Command::FAILURE;
        }

        try {
            $this->loadResolutionContext();

            $subject = $this->getInspectionSubject();

            $this->outputInspection($subject);

            return Command::SUCCESS;
        } catch (InvalidArgumentException $e) {
            $this->io->error($e->getMessage());

            return Command::INVALID;
        }
    }


    /**
     * @return list<string>
     */
    private function getExtensionPackageNames(): array
    {
        return array_map(
            fn (Extension $extension): string => $extension->getPackageName(),
            array_merge(
                $this->themeRepository->getAll(),
                $this->pluginRepository->getAll(),
            ),
        );
    }

    /**
     * @return list<string>
     */
    private function getThemeIds(): array
    {
        if (isset($this->themeModelRepository)) {
            return array_keys(
                $this->themeModelRepository->all()
            );
        } else {
            return [];
        }
    }


    /**
     * Loads the Extension and configured Viewlet that will be queried for resolution.
     *
     * The Extension is determined in the following descending priority:
     *  - explicit Theme Model by ID (--theme) and the associated Theme Package,
     *  - explicit Extension Package by name (--package),
     *  - default Theme Model and the associated Theme Package,
     *  - default Theme Package.
     *
     * @throws Exception
     */
    private function loadResolutionContext(): void
    {
        if (
            $this->input->getOption('theme') !== null &&
            $this->input->getOption('package') !== null
        ) {
            throw new InvalidArgumentException('Provide either an active Theme ID or an Extension Package to use');
        }

        if ($this->input->getOption('theme') !== null) {
            $this->loadThemeModel(
                (int)$this->input->getOption('theme')
            );

            $this->extension = $this->themeModel->package;
        } elseif ($this->input->getOption('package') !== null) {
            $this->loadExtension(
                $this->input->getOption('package')
            );
        } else {
            try {
                $this->loadThemeModel(null);

                $this->extension = $this->themeModel->package;
            } catch (RuntimeException) {
                $this->loadExtension(null);
            }
        }


        $this->loadViewlet($this->extension);


        $this->loadNamespaces(
            $this->input->getOption('namespace')
        );
    }

    /**
     * Loads the Theme Model by ID, or the default Theme Model if null.
     *
     * @throws Exception
     */
    private function loadThemeModel(?int $id): void
    {
        if ($id !== null) {
            $themeModel = $this->themeModelRepository->find($id);

            if ($themeModel === null) {
                throw new InvalidArgumentException('Could not find an active Theme with the specified ID');
            }
        } else {
            $themeModel = $this->themeModelRepository->findDefault();

            if ($themeModel === null) {
                throw new RuntimeException('Could not find active default Theme to use');
            }
        }

        $this->themeModel = $themeModel;
    }

    /**
     * Loads the Extension by Package name, or the default Theme Package if null.
     *
     * @throws Exception
     */
    private function loadExtension(?string $packageName): void
    {
        if ($packageName !== null) {
            $extension = $this->extensionRepositoryRegistry->getExistingExtensionFromPackageName($packageName);

            if ($extension === null) {
                throw new InvalidArgumentException('Could not find Extension Package with the specified name');
            }
        } else {
            $extension = $this->themeRepository->getExisting(DEFAULT_THEME_PACKAGE);

            if ($extension === null) {
                throw new RuntimeException('Could not find default Theme Package to use');
            }
        }

        $this->extension = $extension;
    }

    private function loadViewlet(ViewExtensionInterface $extension): void
    {
        $viewlet = $extension->getViewlet();

        if ($extension instanceof HierarchicalExtensionInterface) {
            $viewlet = new HierarchicalViewlet(
                $extension->getViewlet(),
                $this->extensionRepositoryRegistry->getRepositoryForExtensionClass($extension::class),
                $this->optimization,
            );

            $viewlet->setBaseViewlets(
                array_map(
                    fn (string $codename) => $this->pluginRepository->get($codename)->getViewlet(),
                    $this->mybb->cache?->read('plugins')['active'] ?? [],
                ),
            );
        }

        if ($extension instanceof Theme) {
            $viewlet = ViewletDecorator::decorate(
                $viewlet,
                [
                    PublishableViewlet::class,
                    CompositeViewlet::class,
                ],
            );
        }

        $this->viewlet = $viewlet;
    }

    /**
     * Validates and sets names of namespaces to use.
     *
     * @throws InvalidArgumentException
     */
    private function loadNamespaces(array $namespaces): void
    {
        $validatedNamespaces = [];

        foreach ($namespaces as $value) {
            $namespaceType = NamespaceType::tryFromNamespace($value);

            if ($namespaceType === null) {
                throw new InvalidArgumentException('Invalid namespace `' . OutputFormatter::escape($value) . '`');
            }

            $validatedNamespaces[] = $value;
        }


        $this->namespaces = $validatedNamespaces;

        if (CompositeViewlet::decorates($this->viewlet)) {
            array_map(
                $this->viewlet->applyNamespace(...),
                $this->namespaces,
            );
        }
    }


    /**
     * @throws InvalidArgumentException
     */
    private function getInspectionSubject(): Asset|Resource
    {
        if ($this->input->getOption('resource') && $this->input->getOption('asset')) {
            throw new InvalidArgumentException('Provide either a Resource or an Asset to inspect');
        }

        $packageName = null;


        if ($this->input->getOption('resource')) {
            $locator = $this->getResourceLocatorFromSubjectInput(
                $this->input->getOption('resource'),
                $packageName,
            );

            $subject = $this->viewlet->getResource($locator);
        } elseif ($this->input->getOption('asset')) {
            $locator = $this->getAssetLocatorFromSubjectInput(
                $this->input->getOption('asset'),
                $packageName,
            );

            $subject = $this->viewlet->getAsset($locator);
        } else {
            throw new InvalidArgumentException('Provide either a Resource or an Asset to inspect');
        }


        if (
            $packageName !== null &&
            $packageName !== $this->extension->getPackageName()
        ) {
            $this->io->note(
                sprintf(
                    'Subject from Package `%s` given, but resolution context for inspection is `%s`',
                    OutputFormatter::escape($packageName),
                    OutputFormatter::escape($this->extension->getPackageName()),
                )
            );
        }


        return $subject;
    }

    /**
     * Returns an Asset Locator from interpreted subject input, which may be one of:
     * - HTTP path,
     * - absolute path,
     * - web root-relative path, or
     * - Locator string.
     *
     * @param-out ?string $packageName The implied Package name.
     */
    private function getAssetLocatorFromSubjectInput(
        string $value,
        ?string &$packageName = null,
    ): Locator {
        $appRootAbsolutePath = realpath(MYBB_ROOT);
        $urlPrefix = $this->mybb->settings['bburl'] . '/';

        if (str_starts_with($value, $urlPrefix)) {
            $webRootRelativePath = substr($value, strlen($urlPrefix));
        } elseif (Path::isBasePath($appRootAbsolutePath, $value)) {
            $webRootRelativePath = Path::makeRelative($value, $appRootAbsolutePath);
        } elseif (Path::isBasePath('cache', $value)) {
            $webRootRelativePath = $value;
        } else {
            $webRootRelativePath = null;
        }

        if ($webRootRelativePath !== null) {
            if (Path::isBasePath('cache/themes', $webRootRelativePath)) {
                throw new InvalidArgumentException(
                    'Legacy assets published within `cache/themes/` are not handled by the View system',
                );
            } elseif ($path = ViewletAsset::getLocatorFromPublicPath($webRootRelativePath, $packageName)) {
                return $path;
            } else {
                throw new InvalidArgumentException('Unrecognized Asset path');
            }
        } else {
            try {
                return Locator::fromString($value);
            } catch (LocatorException $e) {
                throw new InvalidArgumentException(
                    sprintf('Invalid Locator string `%s`', $value),
                    previous: $e,
                );
            }
        }
    }

    /**
     * Returns a Resource Locator from interpreted subject input, which may be one of:
     * - absolute path,
     * - application root-relative path, or
     * - Locator string.
     *
     * @param-out ?string $packageName The implied Package name.
     */
    private function getResourceLocatorFromSubjectInput(
        string $value,
        ?string &$packageName = null,
    ): ViewletLocator {
        $appRootAbsolutePath = realpath(MYBB_ROOT);

        if (Path::isBasePath($appRootAbsolutePath, $value)) {
            $appRootRelativePath = Path::makeRelative($value, $appRootAbsolutePath);
        } elseif (Path::isBasePath('inc', $value)) {
            $appRootRelativePath = $value;
        } else {
            $appRootRelativePath = null;
        }

        if ($appRootRelativePath !== null) {
            $locator = $this->getResourceLocatorFromAppRootRelativePath(
                $appRootRelativePath,
                $packageName,
            );

            if ($locator === null) {
                throw new InvalidArgumentException('Unrecognized Resource path');
            }

            return $locator;
        } else {
            try {
                $locator = ViewletLocator::fromString($value);
            } catch (LocatorException $e) {
                throw new InvalidArgumentException(
                    sprintf('Invalid Locator string `%s`', $value),
                    previous: $e,
                );
            }
        }


        return $locator;
    }

    /**
     * @param-out ?string $packageName The implied Package name.
     */
    private function getResourceLocatorFromAppRootRelativePath(
        string $path,
        ?string &$packageName = null,
    ): ?ViewletLocator {
        try {
            $extension = $this->extensionRepositoryRegistry->getExtensionFromPath($path);

            if ($extension instanceof ViewExtensionInterface) {
                $packageName = $extension->getPackageName();

                $resource = $extension->getViewlet()->getResourceFromAbsolutePath(
                    Path::makeAbsolute($path, MYBB_ROOT)
                );

                return $resource->getLocator();
            }
        } catch (InvalidArgumentException | LogicException) {
        }

        return null;
    }


    private function outputInspection(Asset|Resource $subject): void
    {
        $this->io->newLine();

        $this->outputResolutionContext();

        if ($subject instanceof Asset) {
            $this->outputAssetMetadata($subject);
            $this->outputAssetProperties($subject);

            if ($subject instanceof ViewletAsset) {
                $this->outputAssetSourceResources($subject);
            }

            $this->outputAssetReferencesInResources($subject);
        } elseif ($subject instanceof Resource) {
            $this->outputResourceMetadata($subject);

            if ($subject->exists()) {
                if ($this->extension instanceof HierarchicalExtensionInterface) {
                    $this->outputResourceAncestry($subject);
                }
            } else {
                $this->io->note('Resource not found in this resolution context');
            }

            if (PublishableViewlet::decorates($this->viewlet)) {
                $this->outputAssetsPublishedUsingResource($subject);
            }
        }
    }

    private function outputResolutionContext(): void
    {
        $this->io->writeln('<block-title>Resolution Context</block-title>');

        $definitionList = [];


        if (isset($this->themeModel)) {
            $definitionList[] = [
                'Theme Model' => Style::inlineListing([
                    OutputFormatter::escape($this->themeModel->name),
                    '#' . $this->themeModel->id,
                ]),
            ];
        }


        $definitionList[] = [
            'Extension Package' => Style::inlineListing([
                OutputFormatter::escape($this->extension->getPackageName()),
                $this->getExtensionDescription($this->extension),
                Style::filesystemPath($this->extension->getAbsolutePath()),
            ]),
        ];

        if ($this->extension instanceof HierarchicalExtensionInterface) {
            $extensionPackageAncestors = $this->extension->getAncestors($this->themeRepository);

            if ($extensionPackageAncestors) {
                $ancestorsString = implode(
                    ', ',
                    array_map(
                        fn (Extension $extension) =>
                            '<' . Style::extensionStyle($extension) . '>' .
                            OutputFormatter::escape($extension->getPackageName()) .
                            '</>',
                        $extensionPackageAncestors,
                    ),
                );
            } else {
                $ancestorsString = '<minor>None</minor>';
            }

            $definitionList[] = [
                'Extension Ancestors' => $ancestorsString,
            ];
        }


        if (HierarchicalViewlet::decorates($this->viewlet)) {
            $baseViewlets = $this->viewlet->getBaseViewlets();

            if ($baseViewlets) {
                $baseViewletsString = implode(
                    ', ',
                    array_map(
                        fn (ViewletInterface $viewlet) =>
                            '<' . Style::extensionStyle($viewlet->getExtension()) . '>' .
                            OutputFormatter::escape($viewlet->getIdentifier()) .
                            '</>',
                        $baseViewlets,
                    ),
                );
            } else {
                $baseViewletsString = '<minor>None</minor>';
            }

            $definitionList[] = [
                'Base Viewlets' => $baseViewletsString,
            ];
        }


        if ($this->namespaces !== []) {
            $namespacesList = array_map(
                fn (string $namespace) =>
                    OutputFormatter::escape($namespace) .
                    '<minor>(' .
                    NamespaceType::tryFromNamespace($namespace)->name .
                    ')</minor>',
                $this->namespaces,
            );

            $definitionList[] = [
                'Namespaces' => implode(', ', $namespacesList),
            ];
        }


        $this->io->definitionList(...$definitionList);
    }

    private function outputAssetMetadata(Asset $asset): void
    {
        $this->io->writeln(
            sprintf(
                '<block-title>Asset</block-title> %s',
                Style::locator($asset->getLocator()),
            )
        );


        $definitionList = [
            [
                'Asset Class' => match ($asset::class) {
                    StaticAsset::class => 'Static Asset',
                    ViewletAsset::class => 'Viewlet Asset',
                },
            ],
        ];

        $definitionList = array_merge(
            $definitionList,
            $this->getLocatorDefinitionList($asset->getLocator()),
        );

        if (
            $asset instanceof StaticAsset &&
            $asset->getType()
        ) {
            $definitionList[] = [
                'Type' => Style::inlineListing([
                    '<generic>' . $asset->getType()->name . '</generic>',
                ]),
            ];
        }


        if (
            $asset instanceof ViewletAsset ||
            (
                $asset instanceof StaticAsset &&
                $asset->isInApplicationDirectory()
            )
        ) {
            $definitionList = array_merge(
                $definitionList,
                $this->getFileDefinitionList($asset),
            );
        }


        if ($asset instanceof ViewletAsset) {
            $definitionList[] = [
                'Source Resource' => Style::locator(
                    $asset->getResource()->getLocator()
                ),
            ];
            $definitionList[] = [
                'Plain' => Publication::isPlain($asset)
                    ? 'Yes'
                    : 'No',
            ];
            $definitionList[] = [
                'Publication' => match ($this->viewlet->getAssetPublicationBasis($asset)) {
                    PublishableViewlet::PUBLICATION_BASIS_EXPLICIT => '<positive>Explicit</positive>',
                    PublishableViewlet::PUBLICATION_BASIS_IMPLICIT => '<positive>Implicit</positive>',
                    null => '<neutral>No</neutral>',
                },
            ];

            if ($asset->exists()) {
                $definitionList[] = [
                    'Needs Update' => $this->assetNeedsUpdate($asset)
                        ? '<warning>Yes</warning>'
                        : 'No',
                ];
            }
        }

        $definitionList[] = [
            'Public Path' => '<path>' . OutputFormatter::escape($asset->getPublicPath()) . '</path>',
        ];
        $definitionList[] = [
            'URL' => Style::url(
                $asset->getUrl(useCdn: false)
            ),
        ];
        $definitionList[] = [
            'CDN URL' => Style::url(
                $asset->getUrl(useCdn: true)
            ),
        ];


        $this->io->definitionList(...$definitionList);

        $this->io->newLine();
    }

    private function outputAssetProperties(Asset $asset): void
    {
        if (CompositeViewlet::decorates($this->viewlet)) {
            $this->io->writeln('<block-title>Asset Properties</block-title>');

            $this->io->writeln(
                '<code>' .
                OutputFormatter::escape(
                    json_encode(
                        $this->viewlet->getCompositeAssetProperties($asset->getLocator()),
                        JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
                    )
                ) .
                '</code>'
            );

            $this->io->newLine();
        }
    }

    private function outputAssetSourceResources(ViewletAsset $asset): void
    {
        $sourceViewletIdentifiers = Publication::getPublishedAssetSourceViewletIdentifiers($asset);

        $this->io->writeln('<block-title>Contributing Resources</block-title>');

        if ($sourceViewletIdentifiers === [] || $sourceViewletIdentifiers === null) {
            $this->io->writeln(
                '<minor>None</minor>'
            );
            $this->io->newLine();
        } else {
            $rows = [];

            foreach ($sourceViewletIdentifiers as $locatorString => $sourceViewletIdentifier) {
                if (HierarchicalViewlet::decorates($this->viewlet)) {
                    $sourceViewlet = $this->viewlet->getViewlets()[$sourceViewletIdentifier] ?? null;
                } elseif ($this->viewlet->getIdentifier() === $sourceViewletIdentifier) {
                    $sourceViewlet = $this->viewlet;
                } else {
                    $sourceViewlet = null;
                }

                $sourceViewletExtension = $sourceViewlet?->getExtension();

                $rows[] = [
                    Style::locator(ViewletLocator::fromString($locatorString)),
                    '<' . Style::extensionStyle($sourceViewletExtension) . '>' .
                    OutputFormatter::escape($sourceViewletIdentifier) .
                    '</>',
                ];
            }

            $this->io->table(
                [
                    'Locator',
                    'Viewlet',
                ],
                $rows,
            );
        }
    }

    private function outputAssetReferencesInResources(Asset $asset): void
    {
        $this->io->writeln('<block-title>Asset References in Templates</block-title>');


        $searchedTemplateCount = 0;
        $unresolvedExpressionCount = 0;
        $exceptions = [];

        $startTime = microtime(true);

        $results = $this->getAssetReferencesInTemplates(
            $asset->getLocator(),
            $searchedTemplateCount,
            $unresolvedExpressionCount,
            $exceptions,
            outputProgress: $this->input->isInteractive(),
        );

        $endTime = microtime(true);

        $searchTimeSeconds = $endTime - $startTime;


        if ($results === []) {
            $this->io->writeln(
                '<minor>No matched references</minor>'
            );
        } else {
            $rows = [];

            foreach ($results as $result) {
                $argument = OutputFormatter::escape($result['arguments']['locator']);

                if (!empty($result['arguments']['static'])) {
                    $argument .= ', static=true';
                }

                $rows[] = [
                    Style::locator(
                        ViewletLocator::fromResourceContextString(
                            $result['template'],
                            type: ResourceType::TEMPLATE,
                            contextNamespace: null,
                        ),
                    ),
                    $result['line'],
                    '<generic>' . OutputFormatter::escape($result['function']) . '()</generic>',
                    '<code>' . $argument . '</code>',
                ];
            }

            $this->io->table(
                [
                    'Template',
                    'Line',
                    'Function',
                    'Locator Argument',
                ],
                $rows,
            );
        }


        $stats = [
            sprintf(
                'Searched %d Templates in %.4f s',
                $searchedTemplateCount,
                $searchTimeSeconds,
            ),
        ];

        if ($unresolvedExpressionCount !== 0) {
            $stats[] = sprintf(
                '%d unresolved expressions',
                $unresolvedExpressionCount,
            );
        }

        if ($exceptions !== []) {
            $stats[] = sprintf(
                '%d Template processing errors',
                count($exceptions),
            );
        }

        $this->io->writeln(
            '<minor>' . Style::inlineListing($stats) . '</minor>'
        );


        $this->io->newLine();
    }

    /**
     * @throws LocatorException
     */
    private function outputResourceMetadata(Resource $resource): void
    {
        $this->io->newLine();

        $this->io->writeln(
            sprintf(
                '<block-title>Resource</block-title> <value>%s</value>',
                Style::locator($resource->getLocator()),
            )
        );


        $definitionList = [
            ...$this->getLocatorDefinitionList($resource->getLocator()),
            ...$this->getFileDefinitionList($resource),
            [
                'Code Language' => OutputFormatter::escape($resource->getCodeLanguage()?->value),
            ],
        ];

        if ($resource->exists()) {
            $syntaxErrors = $resource->getCodeLanguage()?->getSyntaxErrors(
                $resource->getContent(),
                $resource->getLocator()->getString(),
            );

            if ($syntaxErrors !== null) {
                $definitionList[] = [
                    'Syntax Status' => $syntaxErrors === [] ? 'Valid' : '<negative>Invalid</negative>',
                ];
            }
        } else {
            $syntaxErrors = null;
        }


        if (PublishableViewlet::decorates($this->viewlet)) {
            $definitionList[] = [
                'Publishable' => match ($this->viewlet->getResourcePublicationBasis($resource)) {
                    PublishableViewlet::PUBLICATION_BASIS_EXPLICIT => '<positive>Yes, Explicit</positive>',
                    PublishableViewlet::PUBLICATION_BASIS_IMPLICIT => '<positive>Yes, Implicit</positive>',
                    null => '<neutral>No</neutral>',
                },
            ];
        }


        $this->io->definitionList(...$definitionList);


        if ($syntaxErrors) {
            $this->io->writeln('<block-title>Resource Syntax Errors</block-title>');

            foreach ($syntaxErrors as $exception) {
                $this->io->warning(
                    $exception->getMessage(),
                );
            }
        }
    }

    private function outputResourceAncestry(Resource $resource): void
    {
        $resourceRepository = $resource->getRepository();
        $key = $resource->getRepositoryKey();

        $repositories = $resourceRepository->getRepositories();

        $resolvedRepository = $resourceRepository->getResolvedRepository($key);

        $rows = [];

        $i = 0;

        foreach (array_reverse($repositories) as $repository) {
            $flags = [];

            if ($repository->getHierarchicalIdentifier() === $resolvedRepository?->getHierarchicalIdentifier()) {
                $flags[] = '<signal>✓ Effective</signal>';
            }

            if (!$repository->entityDeclaredInherited($key)) {
                $flags[] = '<negative>✖ Not Inherited</negative>';
            }

            $extension = $repository->viewlet->getExtension();

            $rows[] = [
                ++$i,
                Style::inlineListing([
                    OutputFormatter::escape($extension->getPackageName()),
                    $this->getExtensionDescription($extension),
                ]),
                $repository->has($key)
                    ? Style::inlineListing([
                        'Found',
                        Style::filesystemPath(
                            $repository->get($key)->getAbsolutePath()
                        ),
                    ])
                    : '<minor>Not Found</minor>',
                implode(', ', $flags),
            ];
        }


        $this->io->writeln('<block-title>Resource Ancestry</block-title> <minor>(ascending priority)</minor>');

        $this->io->table(
            [
                '#',
                'Extension Package',
                'Status',
                'Resolution',
            ],
            $rows,
        );

        $this->io->newLine();
    }

    private function outputAssetsPublishedUsingResource(Resource $resource): void
    {
        $assets = $this->viewlet->getAssetsFromResource($resource);

        $this->io->writeln('<block-title>Assets Published Using Resource</block-title>');

        if ($assets === []) {
            $this->io->writeln(
                '<minor>None</minor>'
            );
        } else {
            $rows = [];

            foreach ($assets as $asset) {
                $rows[] = [
                    Style::locator($asset->getLocator()),
                    Style::filesystemPath($asset->getPublicPath()),
                ];
            }

            $this->io->table(
                [
                    'Locator',
                    'Public Path',
                ],
                $rows,
            );
        }

        $this->io->newLine();
    }


    /**
     * Returns information about template expressions involving the given Asset.
     *
     * @param-out list<LoaderError|SyntaxError> $exceptions
     * @return list<array{
     *     template: string,
     *     line: int,
     *     function: string,
     *     arguments: array,
     * }>
     */
    private function getAssetReferencesInTemplates(
        Locator $assetLocator,
        int &$searchedTemplateCount = 0,
        int &$unresolvedExpressionCount = 0,
        array &$exceptions = [],
        bool $outputProgress = false,
    ): array {
        $results = [];


        $templateResources = $this->viewlet->getResources(
            namespaces: $this->namespaces === [] ? null : $this->namespaces,
            resourceTypes: [
                ResourceType::TEMPLATE,
            ],
        );


        $inspector = new Inspector(
            new ViewletLoader($this->viewlet)
        );

        $inspection = new FunctionExpressionInspection();

        $inspection->addTargetFunction('asset', [
            0 => 'locator',
            1 => 'static',
        ]);
        $inspection->addTargetFunction('asset_url', [
            0 => 'locator',
            1 => 'static',
        ]);

        $inspector->addInspection($inspection);


        if ($outputProgress === true) {
            $progressBar = $this->io->createProgressBar();

            $progressBar->setRedrawFrequency(1);
            $progressBar->maxSecondsBetweenRedraws(0.1);
            $progressBar->minSecondsBetweenRedraws(0.1);
        } else {
            $progressBar = null;
        }

        try {
            foreach ($progressBar?->iterate($templateResources) ?? $templateResources as $resource) {
                try {
                    $inspector->inspectTemplate(
                        $resource->getLocator()->getString([
                            'type' => ViewletLocator::COMPONENT_UNSET,
                        ]),
                    );
                } catch (LoaderError | SyntaxError $e) {
                    $exceptions[] = $e;
                }
            }
        } finally {
            $progressBar?->clear();
        }


        foreach ($inspection->getResults() as $call) {
            if (
                !isset($call['arguments']['locator']) ||
                !is_string($call['arguments']['locator'])
            ) {
                continue;
            }

            try {
                if (($call['arguments']['static'] ?? null) === true) {
                    $locatorFromArgument = StaticLocator::fromString($call['arguments']['locator']);
                } else {
                    $resourceLocator = ViewletLocator::fromResourceContextString(
                        $call['template'],
                        ResourceType::TEMPLATE,
                        contextNamespace: null,
                    );

                    $locatorFromArgument = Locator::fromString(
                        $call['arguments']['locator'],
                        [
                            'type' => ViewletLocator::COMPONENT_SET,
                            'namespace' => ViewletLocator::COMPONENT_CONTEXT,
                        ],
                        [
                            'namespace' => $resourceLocator->getNamespace(),
                        ],
                    );
                }

                if ($assetLocator->getString() === $locatorFromArgument->getString()) {
                    $results[] = $call;
                }
            } catch (LocatorException) {
                continue;
            }
        }

        $searchedTemplateCount = count($templateResources);
        $unresolvedExpressionCount = $inspection->getUnresolvedExpressionCount();

        return $results;
    }

    /**
     * @return list<array<string, string>>
     */
    private function getLocatorDefinitionList(Locator $locator): array
    {
        if ($locator instanceof StaticLocator) {
            if ($locator->isRemote()) {
                $pathType = 'Remote';
            } elseif ($locator->isCurrentDirectoryRelative()) {
                $pathType = 'Current Directory-Relative';
            } else {
                $pathType = 'Absolute';
            }

            return [
                [
                    'Path Type' => $pathType,
                ],
            ];
        } elseif ($locator instanceof ViewletLocator) {
            $namespaceType = NamespaceType::tryFromNamespace($locator->getNamespace());

            $namespaceStyle = match ($namespaceType) {
                NamespaceType::GENERIC => 'generic',
                NamespaceType::EXTENSION => 'extension',
            };

            return [
                [
                    'Namespace' => Style::inlineListing([
                        $locator->getNamespace(),
                        '<' . $namespaceStyle . '>' . OutputFormatter::escape($namespaceType->name) . '</>',
                    ]),
                ],
                [
                    'Type' => Style::inlineListing([
                        '<generic>' . $locator->getType()->name . '</generic>',
                        '<path>' . $locator->getType()->getDirectoryName() . '/' . '</path>',
                    ]),
                ],
                [
                    'Group' => $locator->getGroup()
                        ? OutputFormatter::escape($locator->getGroup())
                        : '<minor>-</minor>',
                ],
                [
                    'Filename' => '<path>' . OutputFormatter::escape($locator->getFilename()) . '</path>',
                ],
            ];
        } else {
            throw new UnexpectedValueException();
        }
    }

    /**
     * @return list<array<string, string>>
     */
    private function getFileDefinitionList(Asset|Resource $subject): array
    {
        $definitionList = [
            [
                'Absolute Path' => Style::filesystemPath($subject->getAbsolutePath()),
            ],
            [
                'Exists' => $subject->exists()
                    ? '<positive>Yes</positive>'
                    : '<neutral>No</neutral>',
            ],
        ];

        if ($subject->exists()) {
            $modificationTime = $subject->getModificationTime();

            $definitionList[] = [
                'Size' => get_friendly_size(filesize($subject->getAbsolutePath())),
            ];
            $definitionList[] = [
                'Modification Time' => Style::inlineListing([
                    date('c', $modificationTime),
                    strip_tags(my_date('relative', $modificationTime)),
                ]),
            ];
        }

        return $definitionList;
    }

    private function assetNeedsUpdate(ViewletAsset $asset): bool
    {
        $publication = app()->make(Publication::class, [
            'asset' => $asset,
        ]);

        return $publication->needsUpdate();
    }

    private function getExtensionDescription(Extension $extension): string
    {
        return match (get_class($extension)) {
            Plugin::class => '<fg=yellow>Plugin</>',
            Theme::class => match ($extension->getType()) {
                ThemeType::CORE => '<core>Built-in Theme</core>',
                ThemeType::ORIGINAL => '<extension>Imported Theme</extension>',
                ThemeType::BOARD => '<theme-model>Custom Theme</theme-model>',
            },
        };
    }
}
