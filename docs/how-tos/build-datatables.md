# How-to: Create a Datatable for Modules

The datatable component display all units of a module. E.g. player, user, playlists, templates etc.
It is the standard component which should be displayed when module name only is in the url route.

JavaScript is not used to generate datatables. Most free client based datatables scripts needs the complete table from the database.
The garlic-hub datatables should be able to handle even huge amounts of data like logs. This means our datatables request only the paginated parts from database. 

In the future there will be asyncronous updates. Currently, every click reloads the complete site.

**Remark: the Software is in status of developing. Docs can change**

Unfortunately, this is a complex topic. You had to create four helper classes plus the controller as there are only partly generic functionalities. Especially the list elements part is mostly individual. 

## Required Files and Directories
It is recommended to follow the scripted way and use name the classes identically to make it as easy as possible.

### Controller
Create ShowDatatableController.php in the Controller directory of the module.
To keep the number of dependencies low, only the [DatatableFacadeInterface](../../src/Framework/Utils/Datatable/DatatableFacadeInterface.php) and [DatatableTemplatePreparer](../../src/Framework/Utils/Datatable/DatatableTemplatePreparer.php) should be set as constructor dependency.
### Directories

Create a Datatable dir in the Helper dir of the module. 

### Parameter Class
Derivate a `Parameter` class from [BaseFilterParameters](../../src/Framework/Utils/FormParameters/BaseFilterParameters.php) to sanitizes and handles user inputs.

### DatatableBuilder Class
The  `DatatableBuilder` is responsible for build a data grid (Datatable). It supports the creation of form elements, table columns, pagination and dropdown elements for the data view.Extends this from [AbstractDatatableBuilder](../../src/Framework/Utils/Datatable/AbstractDatatableBuilder.php)

The DatatableBuilder-Class witch required at least the
[BuildService](../../src/Framework/Utils/Datatable/BuildService.php), the Parameter-class, and the `AclValidator` as Constructor-injection

You will need to implement these methods to customize the class with your data:

- abstract public function buildTitle(): void;
- abstract public function configureParameters(int $UID): void;
- abstract public function determineParameters(): void;
- abstract public function collectFormElements(): void;
- abstract public function createTableFields(): static;

Have a look here for example:
[DatatableBuilder](../../src/Modules/Users/Helper/Datatable/DatatableBuilder.php)

### DatatablePreparer Class
Extends [AbstractDatatablePreparer](../../src/Framework/Utils/Datatable/AbstractDatatablePreparer.php)
The `DatatablePreparer` class provides functionality for preparing datatable arrays.
DatatableBuilder-Class will required at least the [PrepareService](../../src/Framework/Utils/Datatable/PrepareService.php)
the modules **AclValidator** and the Parameters as constructor injection.

You will need to implement:
- abstract public function prepareTableBody(array $currentFilterResults, array $fields, $currentUID): array;

and customize it to your needs. 

Have a look here as example [DatatablePreparer](../../src/Modules/Users/Helper/Datatable/DatatablePreparer.php)

### Template and TemplateRenderer
In the template/module-dir create an overview.mustache file and integrate
[datatable.mustache](../../templates/generic/datatable.mustache).

This will give you the opportunity to embed additionally HTML like Context menus, javascript etc
Create the corresponding TemplateRenderer class. This class will also need the Parameter and [Translator.php](../../src/Framework/Localization/Translate/Translator.php) injected by the constructor.

Have a look here how the context menu for creating playlists is realized.
[datatable.mustache](../../templates/player/datatable.mustache)

### ConstructorFacade 
Create a Constructor-Facade Class derivated from interface [DatatableFacadeInterface](../../src/Framework/Utils/DatatableFacadeInterface.php)

This is not a 100% SRP following facade pattern as it will prepare also the array for the UI-Template.