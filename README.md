# Aurora 
## Aurora is NetLife Guru Template Engine

## Overview
This project is a powerful and efficient one-file PHP template engine designed to simplify web development. It requires no external dependencies and offers a straightforward setup process, making it ideal for projects that benefit from a lightweight, easy-to-deploy solution.

## Key Features
- **No Dependencies**: Operates independently without the need for external libraries.
- **Single File Implementation**: Everything you need is contained within a single file, simplifying integration and maintenance.
- **Easy Configuration**: Set up and start using with minimal configuration, allowing you to focus on developing your application.
- **Flexible Templating**: Supports advanced features like custom macros, conditional rendering, and dynamic content integration.

## Getting Started
To get started with this template engine, simply include the PHP file in your project and follow the basic usage instructions below to render your first template.

### Basic Usage

- index.php
```php
require_once("/src/aurora.php");

$aurora = new Nlg\Aurora\Loader([
    'root'  => getcwd() . '/../',
    'views' => '/views',
    'cache' => '/cache',
]);

$aurora->setFiles([
    '/layout.html',
]);

$aurora->setLanguageConstants([
    'hello world' => 'Hello World',
]);

$aurora->setVariables([
    'lang' => 'en',
    'title' => 'Aurora Single Page',
    'year' => date("Y")
]);

$aurora->createCache(false);

print $aurora->render("layout");
```

- views/layout.html
```php
{layout}
    Hello World
{/layout}
```
For detailed configuration options and advanced features, please refer to the documentation provided.

### Documentation

Comprehensive documentation is available that covers detailed configurations and advanced usage scenarios. It provides all the necessary information to fully utilize the capabilities of the template engine.

### Advantages

Using this template engine brings several advantages to your web development process, including:

- Quick setup and easy integration.
- Reduced project complexity with a one-file solution.
- Flexibility in managing dynamic content and UI components

### Conclusion

This template engine is an excellent choice for developers looking for a straightforward, powerful solution to handle web templating needs without the overhead of larger frameworks or libraries. Experiment with it to explore its full potential!




