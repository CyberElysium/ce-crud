# CE-CRUD for Laravel

CE-CRUD is a comprehensive Laravel package designed to streamline the creation of CRUD (Create, Read, Update, Delete) operations in your Laravel applications. With a focus on domain-driven design, CE-CRUD simplifies the process of setting up models, migrations, controllers, services, and facades with minimal effort.

## Features

- **Easy Installation**: Set up CE-CRUD with a simple Composer command.
- **Automatic Setup**: Quickly generate the necessary directory structure for domain-driven design, including Facades and Services.
- **CRUD Generation**: Generate models, migrations, controllers, service files, and facades for your entities with a single command.
- **Customizable Templates**: Use `.stub` files for easy customization of generated files.

## Installation

To install CE-CRUD, run the following command in your Laravel project:

```bash
composer require cyberelysium/ce-crud

After installation, publish the package's configuration (if applicable) and run the initialization command:

php artisan vendor:publish --provider="CyberElysium\CECrud\CECrudServiceProvider"
php artisan install:ce-crud

This command sets up the necessary directory structure and updates your composer.json to support domain-driven design in your project.

Usage
Generating CRUD Operations
To generate CRUD operations for an entity, use:

bash
Copy code
php artisan make:ce-crud EntityName
This will create:

A model named EntityName
A corresponding migration
A controller named EntityNameController
A service file in domain/Services/EntityNameService.php
A facade file in domain/Facades/EntityNameFacade.php
Example
bash
Copy code
php artisan make:ce-crud Banner
Customization
You can customize the templates used for generating services and facades by editing the .stub files located in the stubs directory of the package.

Contributing
Contributions are welcome and will be fully credited. We accept contributions via Pull Requests on GitHub.

Pull Requests
Add tests! - Your patch won't be accepted if it doesn't have tests.
Document any change in behaviour - Make sure the README.md and any other relevant documentation are kept up-to-date.
Consider our release cycle - We try to follow SemVer v2.0.0. Randomly breaking public APIs is not an option.
Create feature branches - Don't ask us to pull from your main branch.
One pull request per feature - If you want to do more than one thing, send multiple pull requests.

# License

The CE-CRUD package is open-sourced software licensed under the MIT license.