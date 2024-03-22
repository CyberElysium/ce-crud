<?php

namespace CyberElysium\CeCrud\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCECrudCommand extends Command {
    protected $signature = 'install:ce-crud';
    protected $description = 'Install the CE-CRUD package';

    public function handle() {
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

        $this->info('CE-CRUD installed successfully. Please run "composer dump-autoload" to refresh autoload.');
    }
}
