# Coding Standards

This document outlines the coding standards and best practices for garlic-hub. Following these conventions ensures 
that our code remains clean, consistent, and easy to read across the entire codebase.

---

## General Guidelines

- Follow most of **PSR-12** standards, but use Allman-Style for if/else, try/catch while etc.
- Use clear, descriptive names for all classes, methods, and variables.
- Keep code modular, with single-responsibility classes and functions.
- Code should be readable and self-explanatory.
- Ensure code is properly documented with `phpdoc` comments if it is not self explaining.

---

## 1. Class Names
Try to find names as short as possible. 

Concrete: If you have a directory named `Settings` do not use `Settings` in class name and let the namespace do the job.

- **Style**: `PascalCase`
- **Convention**: Class names should begin with an uppercase letter, with each subsequent word also capitalized.
- **Examples**:
    - `UserService`
    - `OrderProcessor`
    - `LocaleSubscriber`
### Controller Naming
- Controllers for GUI Actions should have a Show-prefix plus generic a name, see [controller](controller.md)
  **Examples**:
    - `ShowSettingsController`
    - `ShowDatatableController`
    - `ShowComposeController`
  
## 2. Method Names

- **Style**: `camelCase`
- **Convention**: Method names should start with a lowercase letter, with each new word capitalized.
- **Examples**:
    - `getUser()`
    - `processOrder()`
    - `setDefaultLocale()`

## 3. Variables

- **Style**: `camelCase` for most variables, `snake_case` only when interacting with database fields or when clarity requires it.
- **Convention**:
    - Use meaningful names that indicate the purpose of the variable.
    - Constants should be in `UPPER_CASE`.
- **Examples**:
    - `$userList`, `$itemCount`, `$defaultLocale` (standard variables)
    - `$user_id`, `$order_total` (when dealing with database-specific data)
    - `MAX_USER_COUNT`, `DEFAULT_LOCALE` (constants)

## 4. Constants

- **Style**: `UPPER_CASE`
- **Convention**: Use uppercase letters with underscores separating words.
- **Examples**:
    - `MAX_ITEMS`
    - `API_BASE_URL`
    - `DEFAULT_TIMEOUT`

## 5. File Names

### PHP and JavaScript files

- **Style**: `PascalCase`
- **Convention**: File names should match class names exactly and use PascalCase.
  The entry file of a JavaScript page which only wires the classes is `init.js` (`main.js` for the layout).
- **Examples**:
    - `UserService.php`
    - `OrderProcessor.php`
### Templates
small letters and snake case
### Translations
small letters and snake case

### For Documentation Files
 
- **Style**: kebab-case
- **Convention**: File names for documentation should use kebab-case for better readability and consistency, especially in web-based environments.
- **Examples**:
  - `sql-usage.md`
  - `getting-started.md`
  - `api-reference.md`

## 6. Directory Names
### PHP
PascalCase
### Javascript
small letters, kebab-case for more words (`canvas-composer`). A directory which groups classes of one kind
is named like a class: `Controller`, `Views`, `ActionHandler`.
### Templates
small letters and snake case
### Translations
only the two code language code

**Examples**:
- el for greek
- de for german
- en for english

## 7. Function and Parameter Documentation

Types belong into the signature. A `phpdoc` comment is written for what the signature can not say:

- a description, when the purpose is not clear from the name,
- the shape of arrays and generics (`@param array{UID: int, ...}`, `@return list<string>`), PHPStan level 8 needs them,
- `@throws` for the exceptions a caller has to know.

### Example

```php
/**
 * Processes a user's order and returns a confirmation number.
 *
 * @param array{product_id: int, amount: int} $orderDetails
 * @throws OrderNotFoundException
 */
public function processOrder(int $userId, array $orderDetails): string
{
	// Method implementation
}

```

## 8. Code Formatting

- **Indentation**: Use tabs.
- **Braces in Allman style**:
  - Place opening braces `{` on a new line, directly below the control statement or function definition.
  - **Single-line statements**: For single-line statements following a control structure (`if`, `else`, `while`, etc.), braces `{}` are optional. When omitted, place the statement on a new line directly below the control structure and leave an empty line after it for clarity. Example:
    ```php
    if ($condition)
        executeSingleLineAction();

    // continue with code here
    ```
  - Use braces for multi-line statements or to improve readability in complex conditions.
  - JavaScript getter and setter can be written in one line if they are simple liners.
    ```javascript
    get someProperty() { return this.#someProperty; }
    ```

- **Line Length**: Limit lines to 80-120 characters where possible to enhance readability.
- **Spacing**:
    - Add a single blank line between methods to improve readability.
    - Add spaces around operators (e.g., `=`, `+`, `-`, `==`).

### Example

```php
public function processOrder(int $userId, array $orderDetails)
{
    if ($userId > 0)
    {
        // Process the order
    }
    else
    {
        // Handle invalid user ID
    }
}
```

## 9. Error Handling and Exception Management

See [exceptions](exceptions.md): services throw the exceptions of the project, a catch only cleans up or reacts,
the error handlers decide about the answer.

## 10. Naming Conventions for Interfaces and Abstract Classes

- **Interfaces** end with `Interface` (e.g., `PlaylistBuilderInterface`). An interface which names a role
  can go without it (e.g., `AuditLog`, `ScheduledTask`, `FilterDefinition`).
- **Abstract Classes** start with `Abstract` (e.g., `AbstractDatatableBuilder`). The base classes of the
  parameters, the response builders and the repositories are named `Base…` (e.g., `BaseParameters`, `BaseResponseBuilder`).

## 11. Commenting Style and Inline Documentation

Only comment complex logic that are not immediately clear. Also you can write why's about decisions.
do not write babbling comments if it is obvious from the source code.

- **Inline Comments**: Use inline comments sparingly with //
- Write a comment when you temporarily comment out source code
- Keep comments up-to-date with any code changes to ensure accuracy.
- **PHPDoc comments** over methods: see section 7.

## 12. Attribute Placement Standards
o
- **Placement Rule**: Attributes must be placed **after the DocBlock comment**. They should be the first line of code in that block, directly above the method, property, or class they are applied to.

### Example
```php
/**
 * @throws Exception
 */
#[Group('units')]
public function testProcessAddsAttributesAndCallsNextHandler(): void
{
    // Method logic
}
```

## 13. Templates 
Garlic-hub use mustache templates for rendering views, although there is an option to user plain html and theoretically other template-engines. The template-engine should be used only for [separating of concerns](https://en.wikipedia.org/wiki/Separation_of_concerns) and must not include any logic. 

The following are the guidelines for writing templates:

- underline instead of camelCase for block names and variables
- normal variable names in upper case
- blocks in lower case
- use `{{#block_name}}` for opening a block and `{{/block_name}}` for closing a block
- use `{{VARIABLE}}` (escaped) by default, `{{{VARIABLE}}}` only for HTML built by the code itself, see [escaping](escaping.md)
### Example
```html
{{#main_menu}}
    <li>
        <a href="{{URL}}" title="{{LANG_MENU_POINT}}">{{LANG_MENU_POINT}}</a>
    </li>
{{/main_menu}} 
```

## 14. Testing Standards

- Write unit tests for all public methods to validate functionality.
- Use a consistent naming convention for test methods to improve readability.
- Use descriptive names for test methods (e.g., `testProcessOrderReturnsConfirmationNumber`) for clarity.
- Use strict assertions, called static: `static::assertSame`, `static::assertTrue`, `static::assertFalse`.
- Use `createStub()` for collaborators which only return values, `createMock()` only with expectations.
  Name them with the suffix `Stub` or `Mock` (e.g., `userRepositoryStub`).
- Unit tests are isolated and avoid dependencies on external resources (e.g., database, filesystem) for reliable results.

### Test suites

| Suite | Directory | Groups | Purpose |
|-------|-----------|--------|---------|
| `unit` | `tests/Unit` | `units` | Logic of one class, collaborators are stubbed. Must stay fast. |
| `integration` | `tests/Integration` | `db`, `acl`, `auth`, `io` | Real SQLite database, real code, no mocks of our own classes. |
| `blackbox` | `tests/Blackbox` | `blackbox` | The application over HTTP with a PHP built-in server. |
| `architecture` | `tests/Architecture` | | Rules of the code: layers, routes, test groups, JavaScript sinks. |
| `browser` | `tests/Browser` | `browser` | Headless Chromium (Panther) for the JavaScript. Not in the default run: `--testsuite browser`. |

- Unit tests put `#[Group('units')]` above every test method.
- `auth`: login, remember me cookie, passwords and tokens. `io`: real files or other system boundaries without a database.

- Do not test repositories with a mocked query builder. Such a test repeats the code and breaks on every refactoring without finding a wrong query. Write a `db` test instead.
- The database of an integration test is built by the real migrations (`migrations/core`), not by a schema made for tests. A changed schema must make the tests fail.
- Every test gets a fresh in-memory database. Test data comes from SQL scripts in `tests/resources/sql/<topic>/`, loaded with `loadFixture('<topic>/<file>.sql')`. Shared users (admin `1`, anna `2`, bernd `3`) are in `acl/users.sql`.
- The directory mirrors `src/`: `tests/Integration/Modules/<Module>/Repositories/` and `.../Services/`.

**Group `db`** (extend `RepositoryTestCase`): one test class per repository. Test every public method, including the empty result and the neighbours which must stay untouched.

**Group `acl`** (extend `ContainerTestCase`): who sees which rows and who may change or delete what. The test uses the real services from the real DI container and logs in with `actingAs($UID)`. Cover at least the module admin, the owner and a foreign user, and assert that nothing was changed after a rejection. Ids which come from a request must be checked against the object they really belong to, not against another id sent along.

**Groups are not inherited by PHPUnit.** Every concrete test class needs its own attribute, for example `#[Group('db')]` above the class. A class without it is not selected by `--group`.

After writing a test, break the code on purpose (remove a `WHERE`, flip a comparison) and check that the test fails.
