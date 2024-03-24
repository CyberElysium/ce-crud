<?php

namespace Cyberelysium\CeCrud\Console;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class MakeCECrudCommand extends Command
{
    protected $signature = 'make:ce-crud {name}';
    protected $description = 'Generate CRUD operations for a model';

    public function handle()
    {
        $name = $this->argument('name');
        $nameLower = strtolower($name);

        $tableFields = $this->askForTableColumns();

        Artisan::call('make:model', ['name' => $name, '--migration' => true]);

        $this->updateMigrationFile($name, $tableFields);
        $this->updateModelFile($name, $tableFields);

        // Artisan::call('make:controller', ['name' => "{$name}Controller"]);


        $this->createController($name);

        $this->createCrudViewFiles($name, $tableFields);

        // Create the CRUD route file
        $this->createCrudRouteFile($name);

        $serviceStubPath = __DIR__ . '/../stubs/Service.stub';
        $facadeStubPath = __DIR__ . '/../stubs/Facade.stub';

        $serviceStub = file_get_contents($serviceStubPath);
        $facadeStub = file_get_contents($facadeStubPath);

        $serviceContent = str_replace(['{{name}}', '{{nameLower}}'], [$name, $nameLower], $serviceStub);
        $facadeContent = str_replace('{{name}}', $name, $facadeStub);

        $serviceDirectory = base_path("domain/Services");
        $facadeDirectory = base_path("domain/Facades");

        if (!File::isDirectory($serviceDirectory)) {
            File::makeDirectory($serviceDirectory, 0755, true);
        }

        if (!File::isDirectory($facadeDirectory)) {
            File::makeDirectory($facadeDirectory, 0755, true);
        }

        $servicePath = $serviceDirectory . "/{$name}Service.php";
        $facadePath = $facadeDirectory . "/{$name}Facade.php";

        file_put_contents($servicePath, $serviceContent);
        file_put_contents($facadePath, $facadeContent);

        $this->info("CRUD for {$name} generated successfully.");
    }

    protected function askForTableColumns()
    {
        $fields = [];
        while (true) {
            $field = $this->ask('Enter the field name (leave empty to stop adding fields)');
            if (empty($field)) {
                break;
            }
            $type = $this->choice('Select the field type', ['string', 'text', 'integer', 'boolean', 'date'], 0);
            $fields[] = compact('field', 'type');
        }
        return $fields;
    }

    protected function updateMigrationFile($name, $tableFields)
    {
        $nameSnakeCase = Str::plural(Str::snake($name));
        $migrationFileNamePattern = "*_create_{$nameSnakeCase}_table.php";

        $migrationFiles = File::glob(database_path("migrations/{$migrationFileNamePattern}"));

        if (!empty($migrationFiles)) {
            $migrationFile = $migrationFiles[0];

            if (File::exists($migrationFile)) {
                $contents = File::get($migrationFile);

                $schemaUp = "\n            ";
                foreach ($tableFields as $field) {
                    if (in_array($field['field'], ['id', 'timestamps'])) {
                        continue;
                    }
                    $schemaUp .= "\$table->{$field['type']}('{$field['field']}');\n            ";
                }

                $pattern = '/(Schema::create\(.*?\{\s*)(\$table->id\(\);\s*)?(\$table->timestamps\(\);\s*)?/';
                $replacement = '$1$table->id();' . $schemaUp . '$3';

                if (preg_match($pattern, $contents)) {
                    $contents = preg_replace($pattern, $replacement, $contents);
                    File::put($migrationFile, $contents);
                    $this->info("Updated migration: " . basename($migrationFile));
                } else {
                    $this->error("The up method's schema closure in the migration file for {$name} could not be found.");
                }
            } else {
                $this->error("Migration file for {$name} not found.");
            }
        } else {
            $this->error("No migration file pattern matched for {$name}.");
        }
    }


    protected function updateModelFile($name, $tableFields)
    {
        $modelPath = app_path("Models/{$name}.php");
        if (File::exists($modelPath)) {
            $contents = File::get($modelPath);
            $fillableArray = implode("', '", array_column($tableFields, 'field'));
            $fillableString = "protected \$fillable = ['" . $fillableArray . "'];\n";
            $contents = preg_replace('/(class .* extends Model\s*{)/', '$1' . "\n    " . $fillableString, $contents);
            File::put($modelPath, $contents);
        }
    }

    protected function createCrudRouteFile($name)
    {
        $routeDir = base_path("routes/CeCrud");
        File::ensureDirectoryExists($routeDir);

        $namePlural = Str::plural(Str::snake($name));

        $routeFilePath = "{$routeDir}/{$name}.php";
        $routeTemplate = <<<ROUTE
            <?php

            use Illuminate\Support\Facades\Route;
            use App\Http\Controllers\\{$name}Controller;

            Route::prefix('{$namePlural}')->group(function () {
                Route::get('/', [{$name}Controller::class, 'index'])->name('{$namePlural}.index');
                Route::get('/create', [{$name}Controller::class, 'create'])->name('{$namePlural}.create');
                Route::post('/', [{$name}Controller::class, 'store'])->name('{$namePlural}.store');
                Route::get('/{id}/edit', [{$name}Controller::class, 'edit'])->name('{$namePlural}.edit');
                Route::put('/{id}', [{$name}Controller::class, 'update'])->name('{$namePlural}.update');
                Route::delete('/{id}', [{$name}Controller::class, 'destroy'])->name('{$namePlural}.destroy');
            });
            ROUTE;

        File::put($routeFilePath, $routeTemplate);
        $this->info("{$name} route file created successfully.");

        // Include the new route file in web.php
        $webRoutePath = base_path('routes/web.php');
        $routeRequireStatement = "\nrequire __DIR__.'/CeCrud/{$name}.php';";
        if (strpos(File::get($webRoutePath), $routeRequireStatement) === false) {
            File::append($webRoutePath, $routeRequireStatement);
            $this->info("{$name} route file included in web.php successfully.");
        }
    }

    protected function addRouteFileToWeb($name)
    {
        $webRoutesPath = base_path('routes/web.php');

        $routeRequire = "require __DIR__.'/CeCrud/{$name}.php';\n";

        if (!Str::contains(File::get($webRoutesPath), $routeRequire)) {
            File::append($webRoutesPath, "\n" . $routeRequire);
            $this->info("{$name} routes included in web.php successfully.");
        }
    }

    protected function createController($name)
    {
        $controllerStubPath = __DIR__ . '/../stubs/Controller.stub';
        $namePluralLower = Str::plural(Str::snake($name));

        $controllerContent = file_get_contents($controllerStubPath);
        $controllerContent = str_replace(['{{name}}', '{{nameLowerPlural}}'], [$name, $namePluralLower], $controllerContent);

        $controllerPath = app_path("Http/Controllers/{$name}Controller.php");

        if (!File::exists($controllerPath)) {
            File::put($controllerPath, $controllerContent);
            $this->info("{$name}Controller created successfully.");
        } else {
            $this->info("{$name}Controller already exists.");
        }
    }

    protected function createCrudViewFiles($name, $tableFields)
    {
        $namePluralLower = Str::plural(Str::snake($name));
        $dynamicContent = $this->generateDynamicContentForViews($tableFields, $name); // Pass $name here

        $views = ['index', 'create', 'edit'];
        foreach ($views as $view) {
            $stubPath = __DIR__ . "/../stubs/resources/pages/crud/$view.stub";
            $content = file_get_contents($stubPath);

            // Replace placeholders, including a dynamic replacement for the model variable
            $content = str_replace(
                ['{{nameLowerPlural}}', '{{nameLower}}', '{{columnsHeaders}}', '{{columnsData}}', '{{formFields}}'],
                [$namePluralLower, Str::snake(Str::singular($name)), $dynamicContent['columnsHeaders'], $dynamicContent['columnsData'], $dynamicContent['formFields']],
                $content
            );

            $viewPath = resource_path("views/pages/$namePluralLower/$view.blade.php");
            File::ensureDirectoryExists(dirname($viewPath));
            File::put($viewPath, $content);
        }

        $this->info("CRUD view files for $name created successfully.");
    }


    protected function createFileFromStub($stubPath, $filePath)
    {
        if (File::exists($stubPath)) {
            $content = File::get($stubPath);
            File::put($filePath, $content);
            $this->info("Created view: {$filePath}");
        } else {
            $this->error("Stub file does not exist: {$stubPath}");
        }
    }

    protected function generateDynamicContentForViews($tableFields, $name)
    {
        $nameSingularLower = Str::snake(Str::singular($name)); // Adjusted for singular lowercase
        $columnsHeaders = '';
        $columnsData = '';
        $formFields = '';

        foreach ($tableFields as $field) {
            $fieldName = $field['field'];
            $columnsHeaders .= "<th>" . ucfirst($fieldName) . "</th>\n            ";

            // Adjusted for singular lowercase model name in loop
            $columnsData .= "<td>{{ \$" . $nameSingularLower . "->$fieldName }}</td>\n            ";

            // Generate form fields
            $formFields .= "<label for='$fieldName'>" . ucfirst($fieldName) . "</label>\n";
            $formFields .= "<input type='text' name='$fieldName' id='$fieldName' value='{{ old('$fieldName') }}' class='form-control'>\n        ";
        }

        return compact('columnsHeaders', 'columnsData', 'formFields');
    }

}
