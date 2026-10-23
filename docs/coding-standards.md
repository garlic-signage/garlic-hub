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
- Controllers for GUI Actions should have a Show-prefix plus generic a name
  **Examples**:
    - `ShowController`
    - `ShowSettingsController`
    - `ShowOverviewController`
  
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
- **Examples**:
    - `UserService.php`
    - `OrderProcessor.php`
### Templates
small letters and snake case
##ä Translations
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
### Javascript, Templates
small letters and snake case
### Translations
only the two code language code

**Examples**:
- el for greek
- de for german
- en for english

## 7. Function and Parameter Documentation

All functions and methods should be documented with `phpdoc` comments. These comments should include:

- **Description** of the function's purpose.
- **Parameters**: Each parameter should be described with its expected data type and purpose.
- **Return Type**: Describe the return type of the function.

### Example

```php
/**
 * Processes a user's order and returns a confirmation number.
 *
 * @param int $userId The ID of the user.
 * @param array $orderDetails An array of order details.
 * @return string Confirmation number of the processed order.
 */
public function processOrder(int $userId, array $orderDetails): string
{
    // Method implementation
}

```

## 8. Code Formatting

- **Indentation**: Use 4 spaces per indentation level.
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

- Use meaningful exception messages to provide clear context about the error.
- Avoid using generic exceptions (e.g., `Exception`) when specific exceptions are available, as they improve debugging and readability.
- Log errors appropriately, but avoid exposing sensitive information in error messages to maintain security.
- Wrap potentially error-prone code in try-catch blocks where necessary to handle exceptions gracefully and ensure application stability.

### Example

```php
try
{
    $this->processOrder($orderId);
}
catch (OrderNotFoundException $e)
{
    // Log the error and display a user-friendly message
    $logger->error("Order not found: " . $e->getMessage());
    echo "The order could not be found. Please check the ID and try again.";
}
catch (Exception $e)
{
    // Generic catch for unexpected exceptions
    $logger->error("Unexpected error: " . $e->getMessage());
    echo "An unexpected error occurred. Please try again later.";
}
```

## 10. Naming Conventions for Interfaces and Abstract Classes

- **Interfaces** should end with `Interface` (e.g., `UserRepositoryInterface`).
- **Abstract Classes** should start with `Abstract` (e.g., `AbstractOrderProcessor`).

## 11. Commenting Style and Inline Documentation

Only comment complex logic that are not immediately clear. Also you can write why's about decisions.
do not write babbling comments if it is obvious from the source code.

- **Inline Comments**: Use inline comments sparingly with //
- Write a comment when you temporarily comment out source code
- Keep comments up-to-date with any code changes to ensure accuracy.
- **PHPDoc comments** over methods: As PHP 8.3 is required methods do not need any parameter and return value as annotations

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
- Use proper assertions to validate expected results (e.g., `assertEquals`, `assertTrue`, `assertFalse`).
- End mocked classes with Mock as Suffix (e.g., userRepositoryMock)
- Unit tests are isolated and avoid dependencies on external resources (e.g., database, filesystem) for reliable results.

### Unit and integration tests

| Suite | Directory | Groups | Purpose |
|-------|-----------|--------|---------|
| `unit` | `tests/Unit` | `units` | Logic of one class, collaborators are mocked. Must stay fast. |
| `integration` | `tests/Integration` | `db`, `acl` | Real SQLite database, real code, no mocks of our own classes. |

- Do not test repositories with a mocked query builder. Such a test repeats the code and breaks on every refactoring without finding a wrong query. Write a `db` test instead.
- The database of an integration test is built by the real migrations (`migrations/edge`), not by a schema made for tests. A changed schema must make the tests fail.
- Every test gets a fresh in-memory database. Test data comes from SQL scripts in `tests/resources/sql/<topic>/`, loaded with `loadFixture('<topic>/<file>.sql')`. Shared users (admin `1`, anna `2`, bernd `3`) are in `acl/users.sql`.
- The directory mirrors `src/`: `tests/Integration/Modules/<Module>/Repositories/` and `.../Services/`.

**Group `db`** (extend `RepositoryTestCase`): one test class per repository. Test every public method, including the empty result and the neighbours which must stay untouched.

**Group `acl`** (extend `ContainerTestCase`): who sees which rows and who may change or delete what. The test uses the real services from the real DI container and logs in with `actingAs($UID)`. Cover at least the module admin, the owner and a foreign user, and assert that nothing was changed after a rejection. Ids which come from a request must be checked against the object they really belong to, not against another id sent along.

**Groups are not inherited by PHPUnit.** Every concrete test class needs its own attribute, for example `#[Group('db')]` above the class. A class without it is not selected by `--group`.

After writing a test, break the code on purpose (remove a `WHERE`, flip a comparison) and check that the test fails.
