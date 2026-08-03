<?php

declare(strict_types=1);

namespace MyBB\Twig\Inspection;

use Twig\Environment;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * Collects information about function calls in Twig templates.
 */
class FunctionExpressionInspection implements NodeVisitorInterface
{
    /**
     * Mapping of argument Node keys (integer positions or string names) to target argument names, by function name.
     *
     * @var array<string, ?array<non-negative-int|string, string>>
     */
    private array $argumentNodeMapByFunction = [];

    /**
     * @var list<array{
     *   template: string,
     *   line: int,
     *   function: string,
     *   arguments: array<non-negative-int|string, mixed>,
     * }>
     */
    private array $results = [];

    /**
     * A count of function calls with any unresolvable argument value.
     */
    private int $unresolvedExpressionCount = 0;

    public function __construct(
        /**
         * Whether to resolve target argument values from their Node representations.
         *
         * Function expressions with unresolved argument values will be skipped.
         */
        public readonly bool $resolveTargetNodes = true,
    ) {}

    /**
     * Registers a function to search for.
     *
     * @param ?array<non-negative-int, string> $arguments
     *   A mapping of positions to names of target arguments.
     *
     *   If `null`, all of its arguments are included without position-name deduplication.
     *
     *   If any included argument's literal value cannot be resolved, the function call is skipped.
     */
    public function addTargetFunction(string $name, ?array $arguments): void
    {
        if ($arguments === null) {
            $this->argumentNodeMapByFunction[$name] = null;
        } else {
            $this->argumentNodeMapByFunction[$name] = [];

            foreach ($arguments as $argumentPosition => $argumentName) {
                $this->argumentNodeMapByFunction[$name][$argumentPosition] = $argumentName;
                $this->argumentNodeMapByFunction[$name][$argumentName] = $argumentName;
            }
        }
    }

    /**
     * @return list<array{
     *   template: string,
     *   line: int,
     *   function: string,
     *   arguments: array<array-key, mixed>,
     * }>
     */
    public function getResults(): array
    {
        return $this->results;
    }

    public function getUnresolvedExpressionCount(): int
    {
        return $this->unresolvedExpressionCount;
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        $this->inspectNode($node);

        return $node;
    }

    public function leaveNode(Node $node, Environment $env): ?Node
    {
        return $node;
    }

    public function getPriority(): int
    {
        return 0;
    }

    private function inspectNode(Node $node): void
    {
        if (!($node instanceof FunctionExpression)) {
            return;
        }

        $name = $node->getAttribute('name');

        if (!array_key_exists($name, $this->argumentNodeMapByFunction)) {
            return;
        }


        $argumentNodeMap = $this->argumentNodeMapByFunction[$name];

        $mappedArguments = $this->getMappedArguments($node, $argumentNodeMap);

        if ($mappedArguments !== null) {
            $this->results[] = [
                'template' => $node->getTemplateName() ?? '',
                'line' => $node->getTemplateLine(),
                'function' => $name,
                'arguments' => $mappedArguments,
            ];
        } else {
            $this->unresolvedExpressionCount++;
        }
    }

    /**
     * @param Node $node The Node containing an arguments Node.
     * @param ?array<non-negative-int|string, string> $argumentNodeMap
     *   Mapping of argument Node keys (integer positions or string names) to target argument names.
     * @return ?array<non-negative-int|string, mixed> Argument values, or `null` if an argument cannot be resolved.
     */
    private function getMappedArguments(Node $node, ?array $argumentNodeMap): ?array
    {
        $results = [];

        foreach ($node->getNode('arguments') as $argumentNodeKey => $argumentNode) {
            if (
                $argumentNodeMap === null ||
                array_key_exists($argumentNodeKey, $argumentNodeMap)
            ) {
                if ($this->resolveTargetNodes === true) {
                    if (!($argumentNode instanceof ConstantExpression)) {
                        return null;
                    } else {
                        $value = $argumentNode->getAttribute('value');
                    }
                } else {
                    $value = $argumentNode;
                }

                $resultKey = $argumentNodeMap === null
                    ? $argumentNodeKey
                    : $argumentNodeMap[$argumentNodeKey];

                $results[$resultKey] = $value;
            }
        }

        return $results;
    }
}
