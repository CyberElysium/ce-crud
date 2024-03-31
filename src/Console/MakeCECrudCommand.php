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

        $this->addNavLink($name);

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
                Route::post('/store', [{$name}Controller::class, 'store'])->name('{$namePlural}.store');
                Route::get('/{id}/edit', [{$name}Controller::class, 'edit'])->name('{$namePlural}.edit');
                Route::post('/{id}/update', [{$name}Controller::class, 'update'])->name('{$namePlural}.update');
                Route::get('/{id}/delete', [{$name}Controller::class, 'destroy'])->name('{$namePlural}.destroy');
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
        $nameLower = Str::snake($name);
        $namePluralLower = Str::plural(Str::snake($name));

        $controllerContent = file_get_contents($controllerStubPath);
        $controllerContent = str_replace(['{{name}}', '{{nameLowerPlural}}', '{{nameLower}}'], [$name, $namePluralLower, $nameLower], $controllerContent);

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

        $views = ['index', 'create', 'edit'];
        foreach ($views as $view) {
            $dynamicContent = $this->generateDynamicContentForViews($tableFields, $name, $view); // Pass $name here
            $stubPath = __DIR__ . "/../stubs/resources/pages/crud/$view.stub";
            $content = file_get_contents($stubPath);

            // Replace placeholders, including a dynamic replacement for the model variable
            $content = str_replace(
                ['{{nameLowerPlural}}', '{{nameLower}}', '{{name}}', '{{columnsHeaders}}', '{{columnsData}}', '{{formFields}}'],
                [$namePluralLower, Str::snake(Str::singular($name)), $name, $dynamicContent['columnsHeaders'], $dynamicContent['columnsData'], $dynamicContent['formFields']],
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

    protected function generateDynamicContentForViews($tableFields, $name, $view)
    {
        $nameSingularLower = Str::snake(Str::singular($name)); // Adjusted for singular lowercase
        $columnsHeaders = '';
        $columnsData = '';
        $formFields = '';

        if($view === 'index') {
            foreach ($tableFields as $field) {
                $fieldName = $field['field'];
                $fieldType = $field['type'];
                $columnsHeaders .= "<th>" . ucfirst($fieldName) . "</th>\n            ";

                // Adjusted for singular lowercase model name in loop
                $columnsData .= "<td>{{ \$" . $nameSingularLower . "->$fieldName }}</td>\n            ";
            }
        }

        if($view === 'create') {
            foreach ($tableFields as $field) {
                $fieldName = $field['field'];
                $fieldType = $field['type'];

                // Generate form fields
                if ($fieldType === 'boolean') {
                    $formFields .= "<div class='col-md-12'><div class='form-group'>";
                    $formFields .= "<label for='$fieldName'><b>" . ucfirst($fieldName) . "</b></label>\n";
                    $formFields .= "<select name='$fieldName' id='$fieldName' class='form-control form-control-alternative'>\n";
                    $formFields .= "<option value='1' {{ old('$fieldName') == 1 ? 'selected' : '' }}>Yes</option>\n";
                    $formFields .= "<option value='0' {{ old('$fieldName') == 0 ? 'selected' : '' }}>No</option>\n";
                    $formFields .= "</select>\n";
                    $formFields .= "</div></div>\n        ";
                    continue;
                }

                if ($fieldType === 'text') {
                    $formFields .= "<div class='col-md-12'><div class='form-group'>";
                    $formFields .= "<label for='$fieldName'><b>" . ucfirst($fieldName) . "</b></label>\n";
                    $formFields .= "<textarea name='$fieldName' id='$fieldName' class='form-control form-control-alternative'>{{ old('$fieldName') }}</textarea>\n";
                    $formFields .= "</div></div>\n        ";
                    continue;
                }

                if ($fieldType === 'date') {
                    $formFields .= "<div class='col-md-12'><div class='form-group'>";
                    $formFields .= "<label for='$fieldName'><b>" . ucfirst($fieldName) . "</b></label>\n";
                    $formFields .= "<input type='date' name='$fieldName' id='$fieldName' value='{{ old('$fieldName') }}' class='form-control form-control-alternative'>\n";
                    $formFields .= "</div></div>\n        ";
                    continue;
                }

                if ($fieldType === 'integer') {
                    $formFields .= "<div class='col-md-12'><div class='form-group'>";
                    $formFields .= "<label for='$fieldName'><b>" . ucfirst($fieldName) . "</b></label>\n";
                    $formFields .= "<input type='number' name='$fieldName' id='$fieldName' value='{{ old('$fieldName') }}' class='form-control form-control-alternative'>\n";
                    $formFields .= "</div></div>\n        ";
                    continue;
                }

                if ($fieldType === 'string') {
                    $formFields .= "<div class='col-md-12'><div class='form-group'>";
                    $formFields .= "<label for='$fieldName'><b>" . ucfirst($fieldName) . "</b></label>\n";
                    $formFields .= "<input type='text' name='$fieldName' id='$fieldName' value='{{ old('$fieldName') }}' class='form-control form-control-alternative'>";
                    $formFields .= "</div></div>\n        ";
                }
            }
        }

        if($view === 'edit') {
            foreach ($tableFields as $field) {
                $fieldName = $field['field'];
                $fieldType = $field['type'];
                $columnsHeaders .= "<th>" . ucfirst($fieldName) . "</th>\n            ";

                // Adjusted for singular lowercase model name in loop
                $columnsData .= "<td>{{ \$" . $nameSingularLower . "->$fieldName }}</td>\n            ";

                // Generate form fields
                if ($fieldType === 'boolean') {
                    $formFields .= "<div class='col-md-12'><div class='form-group'>";
                    $formFields .= "<label for='$fieldName'><b>" . ucfirst($fieldName) . "</b></label>\n";
                    $formFields .= "<select name='$fieldName' id='$fieldName' class='form-control form-control-alternative'>\n";
                    $formFields .= "<option value='1' {{ \$" . $nameSingularLower . "->$fieldName == 1 ? 'selected' : '' }}>Yes</option>\n";
                    $formFields .= "<option value='0' {{ \$" . $nameSingularLower . "->$fieldName == 0 ? 'selected' : '' }}>No</option>\n";
                    $formFields .= "</select>\n";
                    $formFields .= "</div></div>\n        ";
                    continue;
                }

                if ($fieldType === 'text') {
                    $formFields .= "<div class='col-md-12'><div class='form-group'>";
                    $formFields .= "<label for='$fieldName'><b>" . ucfirst($fieldName) . "</b></label>\n";
                    $formFields .= "<textarea name='$fieldName' id='$fieldName' class='form-control form-control-alternative'>{{ \$" . $nameSingularLower . "->$fieldName }}</textarea>\n";
                    $formFields .= "</div></div>\n        ";
                    continue;
                }

                if ($fieldType === 'date') {
                    $formFields .= "<div class='col-md-12'><div class='form-group'>";
                    $formFields .= "<label for='$fieldName'><b>" . ucfirst($fieldName) . "</b></label>\n";
                    $formFields .= "<input type='date' name='$fieldName' id='$fieldName' value='{{ \$" . $nameSingularLower . "->$fieldName }}' class='form-control form-control-alternative'>\n";
                    $formFields .= "</div></div>\n        ";
                    continue;
                }

                if ($fieldType === 'integer') {
                    $formFields .= "<div class='col-md-12'><div class='form-group'>";
                    $formFields .= "<label for='$fieldName'><b>" . ucfirst($fieldName) . "</b></label>\n";
                    $formFields .= "<input type='number' name='$fieldName' id='$fieldName' value='{{ \$" . $nameSingularLower . "->$fieldName }}' class='form-control form-control-alternative'>\n";
                    $formFields .= "</div></div>\n        ";
                    continue;
                }

                if ($fieldType === 'string') {
                    $formFields .= "<div class='col-md-12'><div class='form-group'>";
                    $formFields .= "<label for='$fieldName'><b>" . ucfirst($fieldName) . "</b></label>\n";
                    $formFields .= "<input type='text' name='$fieldName' id='$fieldName' value='{{ \$" . $nameSingularLower . "->$fieldName }}' class='form-control form-control-alternative'>";
                    $formFields .= "</div></div>\n        ";
                }
            }
        }


        return compact('columnsHeaders', 'columnsData', 'formFields');
    }

    protected function addNavLink($name)
    {
        $namePlural = Str::plural(strtolower($name));
        $namePluralSnake = Str::snake($namePlural);
        $routeName = $namePluralSnake . '.index';
        $sidebarPath = resource_path('views/components/sidebar.blade.php');

        if (File::exists($sidebarPath)) {
            $sidebarContent = File::get($sidebarPath);

            // Check if the sidebar already contains a link for the entity
            if (!str_contains($sidebarContent, "href=\"{{ route('$routeName') }}\"")) {
                // Define the new link HTML
                $newLink = <<<HTML
                    <li class="nav-item">
                        <a class="nav-link {{ in_array(\$curr_url, ['$namePluralSnake.index','$namePluralSnake.create','$namePluralSnake.edit']) ? 'active' : '' }}"
                            href="{{ route('$routeName') }}">
                            <i class="bi bi-layout-text-sidebar-reverse"></i>
                            <span class="nav-link-text">$name</span>
                        </a>
                    </li>
                HTML;

                // Insert the new link before the closing </ul> tag
                $sidebarContent = str_replace('</ul>', "$newLink\n                </ul>", $sidebarContent);
                File::put($sidebarPath, $sidebarContent);

                $this->info("$namePlural link added to sidebar.");
            } else {
                $this->info("$namePlural link already exists in sidebar.");
            }
        } else {
            $this->error('Sidebar file does not exist.');
        }
    }
}
