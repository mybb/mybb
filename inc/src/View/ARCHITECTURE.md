# <img src="https://github.com/mybb/mybb/assets/8020837/93684b83-b1d3-4908-b46b-f753b18fae5b" height="100" align="left"> `MyBB\View` Architecture

_The View domain manages the graphical user interface, themes, layout resources and assets._


## View Extensions

Plugins and Themes implement `ViewExtensionInterface`.

Themes implement `HierarchicalExtensionInterface`, allowing definitions of ancestors.

Theme ancestor information is loaded from Extension manifest files (`manifest.json`).


## Themelets

Interface-related information of each View Extension is stored in a _Themelet_ structure.

Themelets may be divided into **namespaces**, styling separate interfaces, contexts, or independent packages.

Plugins may supply Resources for their own namespace, while Themes may override Resources in any namespace.

Namespace-bound entities carry inheritance information and other metadata in respective JSON files (`resources.json`, `assets.json`), and implement `NamespaceCargo`.

Runtime operations of Themelet entities are handled by the following decorators.


### Hierarchy

`HierarchicalThemelet` provides **vertical resolution and merging** of entities and their properties, according to established inheritance in the following order:
1. Themelets of active Plugins
2. Selected Theme ancestors
3. Selected Theme

Member items inherit from ancestors declared by the Extension by default, but inheritance can be severed for items on the individual and namespace levels.

### Publishing

`PublishableThemelet` returns processed and published assets, and related data.

### Composition

`CompositeThemelet` performs **horizontal resolution and merging** from active namespaces, reconciling references to same Assets.


## Performance

MyBB performs all rendering operations server-side, beginning with hierarchical **resolution**, where individual items and their metadata is accessed according to declared inheritance. These sources are used for the **generation** of items for usage, most impactful during cache warm-up. The **execution** stage involves the execution of Twig Templates and may be further optimized through server configuration.

The table below highlights the practical performance impact (a product of individual computation cost, and usual number of iterations) of these operations.

Stage | Data | Building Cost | Validation Cost
-|-|-|-
Resolution | Extension ancestry | 🟢 Low | 🟢 Low
Resolution | Member properties | 🟢 Low | 🟢 Low
Resolution | Resources | 🔺 High | 🟨 Medium
Generation | Published Assets | 🔺 High | 🟨 Medium
Generation | Twig templates | 🔺 High | 🟨 Medium
Execution | Twig cache opcode | 🟨 Medium | 🟨 Medium


---
## References
- **Design choices & plans:** https://github.com/mybb/meta/blob/main/architecture/mybb-1.9-theme-system.md
