<?php

namespace Cyberelysium\CeCrud\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCECrudCommand extends Command
{
    protected $signature = 'install:ce-crud';
    protected $description = 'Install the CE-CRUD package';

    public function handle()
    {
        $this->info('Installing CE-CRUD...');

        // Create directories
        $directories = [
            base_path('domain'),
            base_path('domain/Facades'),
            base_path('domain/Services'),
        ];

        foreach ($directories as $dir) {
            File::makeDirectory($dir, 0755, true, true);
        }

        // Add namespace to composer.json
        $composerJson = json_decode(file_get_contents(base_path('composer.json')), true);
        $composerJson['autoload']['psr-4']['domain\\'] = 'domain/';
        file_put_contents(base_path('composer.json'), json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Create views structure
        $this->createViewsStructureWithStubs([
            'components' => [
                'navbar.blade.php' => 'stubs/resources/components/navbar.stub',
                'sidebar.blade.php' => 'stubs/resources/components/sidebar.stub',
                'footer.blade.php' => 'stubs/resources/components/footer.stub',
            ],
            'libraries' => [
                'styles.blade.php' => 'stubs/resources/libraries/styles.stub',
                'scripts.blade.php' => 'stubs/resources/libraries/scripts.stub',
            ],
            'layouts' => [
                'main.blade.php' => 'stubs/resources/layouts/main.stub',
            ],
        ]);

        $this->info('CE-CRUD installed successfully. Please run "composer dump-autoload" to refresh autoload.');
    }

    protected function createViewsStructureWithStubs(array $views)
    {
        foreach ($views as $dir => $files) {
            $dirPath = resource_path("views/{$dir}");
            if (!File::isDirectory($dirPath)) {
                File::makeDirectory($dirPath, 0755, true);
            }

            foreach ($files as $fileName => $stubPath) {
                $filePath = "{$dirPath}/{$fileName}";
                if (!File::exists($filePath)) {
                    // Copy the stub to the view file location
                    $stub = File::get(__DIR__ . "/../{$stubPath}");
                    File::put($filePath, $stub);
                    $this->info("Created file: {$filePath}");
                } else {
                    $this->info("File already exists: {$filePath}");
                }
            }
        }

        $componentPath = app_path("View/Components/MainLayout.php");
        $content = "<?php\n\nnamespace App\View\Components;\n\nuse Illuminate\View\Component;\nuse Illuminate\Contracts\View\View;\nuse Closure;\n\nclass MainLayout extends Component\n{\n    public function render(): View|Closure|string\n    {\n        return view('layouts.main');\n    }\n}\n";

        // Ensure the directory exists
        File::ensureDirectoryExists(app_path('View/Components'));

        // Create the component file with your custom content
        File::put($componentPath, $content);

        $this->info("Main layout created successfully.");
    }
}
