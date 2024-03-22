<?php

namespace CyberElysium\CeCrud\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class MakeCECrudCommand extends Command
{
    protected $signature = 'make:ce-crud {name}';
    protected $description = 'Generate CRUD operations for a model';

    public function handle()
    {
        $name = $this->argument('name');
        $nameLower = strtolower($name);

        // Define the paths to the stub files
        $serviceStubPath = __DIR__ . '/../stubs/Service.stub';
        $facadeStubPath = __DIR__ . '/../stubs/Facade.stub';

        // Read the stub files
        $serviceStub = file_get_contents($serviceStubPath);
        $facadeStub = file_get_contents($facadeStubPath);

        // Replace placeholders
        $serviceContent = str_replace(['{{name}}', '{{nameLower}}'], [$name, $nameLower], $serviceStub);
        $facadeContent = str_replace('{{name}}', $name, $facadeStub);

        // Define the paths for the final files
        $servicePath = app_path("domain/Services/{$name}Service.php");
        $facadePath = app_path("domain/Facades/{$name}Facade.php");

        // Write the final files
        file_put_contents($servicePath, $serviceContent);
        file_put_contents($facadePath, $facadeContent);

        $this->info("CRUD for {$name} generated successfully.");
    }
}
