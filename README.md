# CE-CRUD for Laravel

CE-CRUD is a comprehensive Laravel package designed to streamline the creation of CRUD (Create, Read, Update, Delete) operations in your Laravel applications. With a focus on domain-driven design, CE-CRUD simplifies the process of setting up models, migrations, controllers, services, and facades with minimal effort.

## Features

- **Easy Installation**: Set up CE-CRUD with a simple Composer command.
- **Automatic Setup**: Quickly generate the necessary directory structure for domain-driven design, including Facades and Services.
- **CRUD Generation**: Generate models, migrations, controllers, service files, and facades for your entities with a single command.
- **Customizable Templates**: Use `.stub` files for easy customization of generated files.

## Installation

To install CE-CRUD, run the following command in your Laravel project:

``` composer require cyberelysium/ce-crud ```

After installation, publish the package's configuration:

``` php artisan vendor:publish --provider="Cyberelysium\CeCrud\CeCrudServiceProvider" ```

Run the initialization command:

``` php artisan install:ce-crud ```

This command sets up the necessary directory structure and updates your composer.json to support domain-driven design in your project.

Then Please run this command to refresh autoload :

``` composer dump-autoload ```

## Usage

Generating CRUD Operations
To generate CRUD operations for an entity, use:

``` php artisan make:ce-crud EntityName ```

#### This will create:

A model named EntityName
A corresponding migration
A controller named EntityNameController
A service file in domain/Services/EntityNameService.php
A facade file in domain/Facades/EntityNameFacade.php

## Example

``` php artisan make:ce-crud Banner ```

## Customization

You can customize the templates used for generating services and facades by editing the .stub files located in the stubs directory of the package.

## License

The CE-CRUD package is open-sourced software licensed under the MIT license.