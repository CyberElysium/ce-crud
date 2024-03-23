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

        Artisan::call('make:controller', ['name' => "{$name}Controller"]);

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
}
