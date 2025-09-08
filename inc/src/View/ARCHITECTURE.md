# <img src="https://github.com/mybb/mybb/assets/8020837/93684b83-b1d3-4908-b46b-f753b18fae5b" height="60" align="center"> `MyBB\View` Architecture

_The View domain manages the graphical user interface, themes, layout resources and assets._

## Overview
To render pages for web browsers, MyBB's core and Plugins use _View_ — by calling the `Runtime` object and helper functions.

The web GUI is built using files (_Resources_) provided by active Themes and Plugins. Resources from each Extension's GUI package (_Viewlet_) are included in an inheritance hierarchy queried to return metadata, publish Assets, and render HTML.


## Viewlets
A **Viewlet** contains files defining the visual appearance of the GUI.

The files are organized into **namespaces**, which style separate interfaces and contexts. Each namespace may contain:
- **Resources** — files used for server-side rendering, or for generating client-side Assets,
- **Resource Properties** (`resources.json`) — metadata files for contained Resources,
- **Asset Properties** (`assets.json`) — metadata files for generating and managing Assets.

Resources are organized by Type, and may be grouped in arbitrary directories, while metadata files are stored at the top level.

##### **Example: A Viewlet Directory Tree**
```
frontend/
    images/
    scripts/
    styles/
    templates/

    assets.json
    resources.json
acp/
    ...
```

### View Extensions
Two types of Extensions provide Viewlets (`ViewExtensionInterface`):
- A **Plugin** may supply a Viewlet (in a `view/` subdirectory) to provide default styling for its interface.

  Plugins' Resources are placed in a dedicated namespace for the Plugin (`ext.`…), and can be overridden by Themes.

- A **Theme** has its own Viewlet (in the same directory), and may style any namespace.

  Each Theme has an implicit type, according to its package name prefix:
  - **Board Theme** (`theme.`…) — a local package authored by administrators,
  - **Original Theme** — an Extension with assigned codename, authored by Theme creators,
  - **Core Theme** (`core.`…) — distributed with, and authored by MyBB.

### Inheritance
Themes, in addition to overriding Plugins' default styling, may inherit and override other Themes present in the installation (`HierarchicalExtensionInterface`).

A Theme's manifest file may include an `inherits` declaration, referencing one or more Themes. A Theme can inherit from Themes of the same or higher type (an _Original Theme_ may only inherit from another _Original Theme_ or a _Core Theme_). The Theme ancestry is built recursively.

The effective **inheritance chain** is defined in the following order, by decreasing priority:
  1. The reference Theme
  2. Ancestors of the reference Theme (from closest to furthest, ending with a Core Theme)
  3. Viewlets of active Plugins

<br>

**Diagram: Viewlet Inheritance Path**

```mermaid
flowchart RL
  subgraph PluginsGraph[Base Viewlets]
      Plugins@{shape: processes, label: "Plugins"}
      Plugins:::plugin

      PluginViewlets@{shape: processes, label: "Plugin Viewlets"}
      PluginViewlets:::viewlet
      PluginViewlets---Plugins
  end
  PluginsGraph:::domainGraph
  PluginsGraph:::baseGraph

  subgraph ThemesGraph[Theme Viewlets]
      CoreTheme[Core Theme]
      CoreTheme:::coreTheme

      OriginalThemes@{shape: processes, label: "Original Themes"}
      OriginalThemes:::originalTheme
      OriginalThemes--inherit from same or higher types-->CoreTheme

      BoardThemes@{shape: processes, label: "Board Themes"}
      BoardThemes:::boardTheme
      BoardThemes--inherit from same or higher types-->OriginalThemes
  end
  ThemesGraph:::domainGraph
  ThemesGraph:::themesGraph
  ThemesGraph==>PluginViewlets

  HierarchicalViewlet{{Hierarchical Viewlet}}
  HierarchicalViewlet:::viewlet
  HierarchicalViewlet==>ThemesGraph


  class HierarchicalViewlet colorPrimaryBlock
  class Plugins,PluginViewlets colorBlock
  class CoreTheme,OriginalThemes,BoardThemes colorBlock

  classDef domainGraph rx:10px,fill:currentColor,fill-opacity:0.1,stroke:currentColor,font-weight:bold
  classDef colorPrimaryBlock stroke:none,fill:currentColor,color:white
  classDef colorBlock stroke:currentColor,fill:none

  classDef baseGraph color:#e1711f
  classDef themesGraph color:#7d659e

  classDef viewlet color:hotpink
  classDef plugin color:#e1711f
  classDef coreTheme color:#007fd0
  classDef originalTheme color:#e1711f
  classDef boardTheme color:#7d659e
```

<br>
<br>

Entities and Properties are inherited by default. Inheritance can be severed on a namespace and item basis in each Properties file.

The resulting resolved hierarchy is used as a virtual source for building and rendering the GUI.


## Entities
The metadata of _View_ entities — including inheritance declarations — is stored in the respective JSON files (`resources.json`, `assets.json`).

### Properties
The JSON files include shared properties (applied to all entities) at the top level, and entity-specific properties grouped under the corresponding key (`assets`, `resources`).
```json5
{
  // shared properties

  "<NAME>": {
    "<ENTITY-KEY>": {
      // entity properties
    },
  },
}
```

#### `inherits`
Shared, and entity-specific properties may include the `inherits` key, set to one of the following options:
- ##### `null`
  Default inheritance (`true`).

- ##### `true`
  Inherits from ancestors.

- ##### `false`
  Does not inherit from ancestors.

- ##### `{list<string>}`
  An array of ancestor Package names, from furthest to closest.

  For example:
  ```json
  "inherits": [
    "core.base",
    "downloaded_theme"
  ]
  ```

### Cargo
Entities use the repository pattern with logic provided by `Cargo` classes, which return properties and instances according to resolved inheritance.

Entities contained in namespaces use the specialized `NamespaceCargo` classes, which add Viewlet-related logic.

##### **Diagram: Cargo Abstraction**
```mermaid
block-beta
    columns 4

    block:LevelTitles
      columns 1
      space

      space
      LevelTitleGeneric["Cargo"]
      space
      space
      LevelTitleNamespace["Viewlet\nNamespace Cargo"]
      space
      space
      LevelTitleConcrete["Viewlet Entities"]
      space
    end

    block:Entities
      columns 1
      ClassEntity["Entity Class"]

      GenericEntityInterface["Entity Interface"]
      space
      GenericEntityTrait["Entity Trait"]
      space
      NamespaceEntityTrait["Entity Trait"]
      space
      Resource["Resource"]
      space
      HierarchicalResource["Hierarchical Resource"]

      GenericEntityTrait--"partially satisfies"-->GenericEntityInterface
      NamespaceEntityTrait--"uses"-->GenericEntityTrait
      Resource--"uses"-->NamespaceEntityTrait
      HierarchicalResource--"extends"-->Resource
    end

    block:Repositories
      columns 1
      ClassRepository["Repository Class"]

      GenericRepositoryInterface{{"Repository Interface"}}
      space
      GenericRepository{{"Repository"}}
      space
      NamespaceRepository{{"Repository"}}
      space
      ResourceRepository{{"Resource Repository"}}
      space
      space

      GenericRepository--"implements"-->GenericRepositoryInterface
      NamespaceRepository--"extends"-->GenericRepository
      ResourceRepository--"extends"-->NamespaceRepository
    end

    block:HierarchicalRepositories
      columns 1
      ClassHierarchicalRepository["Hierarchical Repository Class"]

      space
      space
      GenericHierarchicalRepository{{"Hierarchical Repository"}}
      space
      NamespaceHierarchicalRepository{{"Hierarchical Repository"}}
      space
      ResourceHierarchicalRepository{{"Hierarchical\nResource Repository"}}
      space
      space

      GenericHierarchicalRepository--"implements"-->GenericRepositoryInterface
      NamespaceHierarchicalRepository--"extends"-->GenericHierarchicalRepository
      ResourceHierarchicalRepository--"extends"-->NamespaceHierarchicalRepository
    end

    Resource-.-ResourceRepository
    ResourceRepository-.->Resource
    HierarchicalResource-.->ResourceHierarchicalRepository
    ResourceHierarchicalRepository-.->HierarchicalResource


    class LevelTitles,LevelTitleGeneric,LevelTitleNamespace,LevelTitleConcrete noframe
    class ClassEntity,ClassRepository,ClassHierarchicalRepository noframe
    class Entities,Repositories,HierarchicalRepositories type

    class LevelTitleNamespace namespace
    class LevelTitleConcrete resourceRepository

    class GenericEntityInterface interface
    class GenericEntityTrait abstract
    class NamespaceEntityTrait abstract
    class NamespaceEntityTrait namespace
    class Resource resource
    class HierarchicalResource logicalResource

    class GenericRepositoryInterface interface
    class GenericRepository abstract
    class NamespaceRepository namespace
    class NamespaceRepository abstract
    class ResourceRepository resourceRepository

    class GenericHierarchicalRepository abstract
    class NamespaceHierarchicalRepository namespace
    class ResourceHierarchicalRepository resourceRepository

    classDef default stroke:gray,fill:none

    classDef resource fill:mediumpurple,color:white
    classDef logicalResource fill:none,stroke:mediumpurple,color:mediumpurple
    classDef resourceRepository stroke:mediumpurple,color:mediumpurple
    classDef namespace stroke:darkkhaki,color:darkkhaki

    classDef interface stroke-dasharray:2 2,fill:none,font-style:italic
    classDef abstract stroke-dasharray:5 5

    classDef noframe stroke:none,fill:none
    classDef type stroke:none,fill:gray,fill-opacity:0.1
```


## Locators
References to Resources and Assets use _Locators_, saved as strings in configuration files and Resources.

**Viewlet Locators** refer to entities within a Viewlet structure, resolved according to the inheritance hierarchy, or for a specific Package.

Its components correspond to the directory structure within a Viewlet, and include the namespace (prefixed with `@`), Resource Type, Resource group, and Resource name. Depending on the place of use, some components may be implied by context.

For example, Locators in Templates are resolved in relation to the containing Resource:
```twig
{# Full Viewlet Locator #}
{% include '@frontend/templates/partials/header/avatar.twig' %}

{# Type "template" implied when in templates context #}
{% include '@frontend/partials/header/avatar.twig' %}

{# same namespace implied #}
{% include 'partials/header/avatar.twig' %}

{# same group (directory) implied #}
{% include 'avatar.twig' %}
```

<br>

**Static Locators** are non-interpreted references to local or remote files not managed by _View_.

A string is a Static Locator if it:
- starts with `/` (domain root),
- starts with `./` (MyBB root),
- starts with `../` (relative to visited directory), or
- contains `://`.

Locators referencing static files (e.g. in `jscripts/`) use the `./` format, and have the board/CDN URL prepended in the HTML output.

For example:
```twig
{{ asset('./jscripts/general.js') }}
{{ asset('../site.css') }}
{{ asset('/manifest.json') }}
{{ asset('https://example.com/logo.png') }}
```


## Data Sharing
A shared key-value store for custom runtime data is available in various execution contexts.

The API functions `get()` and `set()`, accessible in PHP and Twig, allow Plugins to use custom data in Templates.

Compared to global variables in Twig, which cannot be declared after the initialization of `Twig\Environment` or loaded after the initialization of extensions, the uniform functions provide consistent behavior regardless of used plugin hooks.


## Templates
Viewlets may include **Templates** — Resources processed server-side with [Twig](https://twig.symfony.com/).

Templates are rendered using the `template()` function, which accepts a Viewlet Locator relative to the main namespace, and returns the resulting HTML.

The Locator is used to establish the filesystem path to the Template file, and passed to Twig (`ViewletLoader`).

The Twig runtime renders the template using the configured options, Twig extensions, and variables; and cached.


## Assets
Returned HTML pages rely on **Assets** — additional files accessible to the web browser (e.g. styles, scripts, images). _View_ uses Viewlet contents and Asset declarations to prepare and include them automatically.

### Asset Publishing
Viewlet Resources are used to create local Assets. Assets may be published:
- explicitly — by declaring Assets and source Resource(s) in the Asset Properties file or function calls, or
- implicitly — by adding Resources of common web file types to the Viewlet.

_View_ may **process** them to desired formats (e.g. SCSS to CSS), and **publish** them by placing the resulting files in a web-accessible directory.

### Asset Management
_View_ manages:
  - **attaching** of Assets and their dependencies to the DOM for specified requests,
  - **insertion** of Assets into the DOM with the necessary HTML tags.


Asset Management supports both Viewlet Assets and non-system Assets (external URLs or hardcoded local paths).

Additionally, _legacy_ stylesheets (stored in the database and associated with the activated theme), which may be inserted by Plugins, are attached depending on the accompanying conditions.

#### Declarations
Asset Management features may be declared using:
- ##### **Asset Properties** (`assets.json`)
  The preferred method, as the data is always accessible. This allows MyBB to manage all aspects of the lifecycle — from all-at-once publishing to DOM inclusion with the correct HTML attributes.

- ##### **API functions**
  PHP and Twig Template functions used for dynamic declarations:
  - `asset()`, used to declare, insert, and render Assets for local HTML inclusion (`local: true`).
  - `asset_url()`, used to access the web-accessible Asset path.

<br>
The available Asset Management functionality depends on the declaration type.

##### **Table: Asset Declarations Types**
Declaration Type | ℹ Metadata | ℹ Content | 🚥 Path | 🚥 HTML | 🚥 Placement
-|-|-|-|-|-
Asset Properties | ✅ Yes | ✅ Yes | ✅ Yes | ✅ Yes | ✅ Yes
`asset()` | ⚠️ Dynamic | ✅ Yes | ✅ Yes | ✅ Yes | ✅ Yes
`asset(local: true)` | ⚠️ Dynamic | ✅ Yes | ✅ Yes | ✅ Yes | ❌ No
Hardcoded `asset_url()` | ❌ No | ✅ Yes | ✅ Yes | ❌ No | ❌ No
Hardcoded | ❌ No | ❌ No | ❌ No | ❌ No | ❌ No

The application has:
- _ℹ Awareness_:
  - **Metadata**: can access all metadata assigned to an Asset
  - **Content**: can query the Asset content (stored in a separate file)
- _🚥 Control_:
  - **HTML**: controls the HTML representing the Asset in the DOM
  - **Path**: controls the path referenced in the DOM
  - **Placement**: controls where in the DOM the Asset is inserted

<br>

##### **Diagram: Asset Publishing and Management**
```mermaid
flowchart BT
    subgraph SourceViewlet[Source Viewlet]

        Resources@{shape: processes, label: "Resources"}
        Resources:::resource

        PublishableResources@{shape: processes, label: "Publishable Resources"}
        PublishableResources:::abstract
        PublishableResources:::resource
        PublishableResources-->Resources

        AssetProperties@{shape: doc, label: "Asset Properties"}
        AssetProperties:::assetProperties

        AssetAttachingDirectives@{shape: doc, label: "Attaching directives"}
        AssetAttachingDirectives:::assetProperties
        AssetAttachingDirectives-->AssetProperties

        AssetPublicationDirectives@{shape: doc, label: "Publication directives"}
        AssetPublicationDirectives:::assetProperties
        AssetPublicationDirectives-->AssetProperties
    end
    SourceViewlet:::domainGraph
    SourceViewlet:::viewletGraph

    subgraph AssetManagement[Asset Management]
        StaticAssets@{shape: processes, label: "Static Assets"}
        StaticAssets:::staticAsset
        StaticAssets:::abstract

        subgraph AssetPublishing["Asset Publishing"]
            ExplicitSources@{shape: processes, label: "Explicitly published"}
            ExplicitSources:::abstract
            ExplicitSources:::resource
            ExplicitSources-->PublishableResources

            ImplicitSources@{shape: processes, label: "Implicitly published"}
            ImplicitSources:::abstract
            ImplicitSources:::resource
            ImplicitSources-->PublishableResources

            ProcessedResources@{shape: processes, label: "Processed Resources"}
            ProcessedResources:::processedResource
            ProcessedResources-->ExplicitSources
            ProcessedResources-->ImplicitSources

            PublishedViewletAssets@{shape: processes, label: "Published Viewlet Assets"}
            PublishedViewletAssets:::publishedViewletAssets
            PublishedViewletAssets-->ProcessedResources
        end
        AssetPublishing:::domainGraph
        AssetPublishing:::assetPublishingGraph

        AssetPublishing-->AssetPublicationDirectives

        PublishedAssets@{shape: processes, label: "Published Assets"}
        PublishedAssets:::abstract
        PublishedAssets:::asset
        PublishedAssets-->PublishedViewletAssets
        PublishedAssets-->StaticAssets

        AssetCall[["asset()"]]
        AssetCall:::subroutine
        AssetCall-->PublishedAssets

        AssetUrlCall[["asset_url()"]]
        AssetUrlCall:::subroutine
        AssetUrlCall-->PublishedAssets

        AttachedAssets@{shape: processes, label: "Attached Assets"}
        AttachedAssets:::asset
        AttachedAssets-->AssetAttachingDirectives
        AttachedAssets-->AssetCall

        InsertedAssets@{shape: processes, label: "Inserted Assets"}
        InsertedAssets:::asset
        InsertedAssets:::abstract
        InsertedAssets-->AssetCall
        InsertedAssets-->AttachedAssets
    end
    AssetManagement:::domainGraph
    AssetManagement:::assetManagementGraph

    DOM([DOM])
    DOM-->InsertedAssets
    DOM-->AssetUrlCall
    DOM-.->PublishedAssets


    class AssetProperties,Resources,PublishedViewletAssets colorPrimaryBlock
    class PublishableResources,AssetAttachingDirectives,AssetPublicationDirectives colorBlock
    class ExplicitSources,ImplicitSources,ProcessedResources colorBlock
    class PublishedAssets,AssetCall,AssetUrlCall,AttachedAssets,InsertedAssets,StaticAssets,HttpResponse colorBlock

    classDef default fill:none,stroke:gray,color:gray

    classDef domainGraph rx:10px,fill:currentColor,fill-opacity:0.1,stroke:currentColor,font-weight:bold
    classDef colorPrimaryBlock stroke:none,fill:currentColor,color:white
    classDef colorBlock stroke:currentColor,fill:none

    classDef abstract stroke-dasharray:5 5
    classDef subroutine font-family:monospace

    classDef viewletGraph color:orchid
    classDef assetManagementGraph color:seagreen
    classDef assetPublishingGraph color:teal

    classDef resource color:mediumpurple
    classDef processedResource color:steelblue
    classDef asset color:seagreen
    classDef publishedViewletAssets color:teal
    classDef assetProperties color:olivedrab
```


## Runtime
The `Runtime` object accepts and manages context data — including the reference Theme (the global default, group/forum setting, or user preference) — and provides View-related features.

### Viewlet Decoration
`Runtime` uses the Viewlet from the reference Theme extended with the following functionality:

- #### Hierarchy
  `HierarchicalViewlet` provides **vertical resolution and merging** of entities and their properties.

  It accepts Plugin Viewlets as as the inheritance base, and uses the Theme's defined inheritance.

  The Hierarchical Viewlet functions as a single set of metadata and Resources for reading, and a dispatcher for write operations.

- #### Publishing
  `PublishableViewlet` provides **Asset publishing** information and features.

- #### Composition
  `CompositeViewlet` performs **horizontal resolution and merging** from active namespaces, reconciling references to the same Assets to render the page.


## Performance
MyBB performs all rendering operations server-side, beginning with hierarchical **resolution**, where individual items and their metadata is accessed according to declared inheritance. These sources are used for the **generation** of items for usage, most impactful during cache warm-up. The **execution** stage involves the execution of Twig Templates and may be further optimized through server configuration.

The table below highlights the practical performance impact (a product of individual computation cost, and usual number of iterations) of these operations.

##### **Table: Performance Impact of View Operations**
Stage | Data | Sources | Building Cost | Validation Target | Validation Cost
-|-|-|-|-|-
**Ancestry** | Source Viewlets | Extension manifests | 🟢 Low | Extension manifest stamp | 🟢 Low
**Resolution** | Entity Properties | Property files | 🟢 Low | Ancestry; Property file stamps | 🟢 Low
**Resolution** | Entities | Property files, Entity files | 🟨 Medium | Ancestry; Property file stamps | 🟨 Medium
**Generation** | Assets | Resources | 🔺 High | Entity Properties; Resources | 🟨 Medium
**Generation** | Templates | Resources | 🔺 High | Resources | 🟨 Medium
**Execution** | Template opcode | Templates | 🟨 Medium | Twig cache; _Opcache-dependent_ | 🟨 Medium

### Cache Validation
Generated content is refreshed using metadata of entities depended upon (bottom-to-top). File modification times or checksums are used as _stamps_, validated according to the configured [`Optimization`](Optimization.php) level. Higher levels offer better performance.

With 🟩`BALANCED` or lower, the system detects high-level changes (i.e. in manifests or Property files), by resolving and generating affected Templates and Assets when necessary.

With 🟦`WATCH` or lower, changes in individual Resources are propagated automatically.


##### **Table: Optimization Mode Required to Propagate Changes**
&ZeroWidthSpace; | Extension ancestry change | Resource change | Resource Properties change | Asset Properties change
-|-|-|-|-
Re-generate **Template** | ≤ 🟩`BALANCED` | ≤ 🟦`WATCH` | ≤ 🟩`BALANCED` | n/a
Re-generate **Asset** | ≤ 🟩`BALANCED` | ≤ 🟦`WATCH` | ≤ 🟩`BALANCED` | ≤ 🟩`BALANCED`


## [ABNF](https://datatracker.ietf.org/doc/html/rfc5234)
```abnf
; Extensions
extension-codename = 1*( a-z / "_" )
package-version    = 1*( DIGIT / a-z / "." / "-" ) ; format supported by PHP's version-compare()

plugin-package-name    = extension-codename
theme-package-name     = extension-codename      ; distributed Theme
                       / "theme." 1*DIGIT        ; Board Theme
                       / "core." 1*( a-z / "_" ) ; Core Theme

plugins-directory-path    = "inc/plugins"
themes-directory-path     = "inc/themes"
extension-directory-path  = plugin-directory-path
                          / theme-directory-path

plugin-directory-path = plugins-directory-path "/" plugin-package-name
theme-directory-path  = themes-directory-path "/" theme-package-name

extension-manifest-file-path   = extension-directory-path "/manifest.json"

; Viewlets
viewlet-directory-path = plugin-directory-path "/view"
                        / theme-directory-path

; Resources
namespace = 1*( a-z / "_" )           ; Generic Namespace
          / "ext." extension-codename ; Extension Namespace

namespace-path = namespace "/"
               / "" ; direct (single-namespace Plugin Viewlet)

resource-type = "images"
              / "scripts"
              / "styles"
              / "templates"

resource-group = 1*( a-z / "_" / "/" )
resource-filename = 1*VCHAR "." 1*VCHAR

resource-path = namespace-path resource-type "/" [resource-group "/"] resource-filename
absolute-resource-path = <web-root-directory> "/" viewlet-directory-path "/" resource-path

resource-properties-file-path = viewlet-directory-path "/" namespace "/resources.json"
asset-properties-file-path = viewlet-directory-path "/" namespace "/assets.json"

; Locators
explicit-directory-path = ( "/" / "./" / "../" ) *VCHAR
remote-path = "//" *VCHAR
            / *VCHAR "://" *VCHAR
static-locator = explicit-directory-path / remote-path

viewlet-locator = ["@" namespace "/"] [resource-type "/"] [resource-group "/"] resource-filename
```

---
## References
- **Design choices & plans:** https://github.com/mybb/meta/blob/main/architecture/mybb-1.9-theme-system.md
