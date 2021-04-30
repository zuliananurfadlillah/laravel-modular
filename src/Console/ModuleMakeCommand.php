<?php


namespace Dptsi\Modular\Console;


use Dptsi\Modular\Facade\ModuleManager;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ModuleMakeCommand extends GeneratorCommand
{
    protected $name = 'module:make';

    protected $description = 'Create new module';

    protected $type = 'Module';

    protected function replaceClass($stub, $name)
    {
        return $stub;
    }

    protected function getStub()
    {
        return __DIR__ . '/../stubs/module.stub';
    }

    protected function getPath($name)
    {
        $name = Str::replaceFirst($this->rootNamespace(), '', $name);

        return $this->laravel['path'] . '/' . str_replace('\\', '/', $name) . '/Module.php';
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return parent::getDefaultNamespace($rootNamespace) . '\\Modules';
    }

    protected function getNamespace($name)
    {
        return $name;
    }

    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the module'],
        ];
    }

    protected function getOptions()
    {
        return [
            [
                'skeleton',
                'S',
                InputOption::VALUE_OPTIONAL,
                'Folder structure to be applied to the module',
                'mvc',
            ],
            [
                'database',
                'D',
                InputOption::VALUE_OPTIONAL,
                'Database driver to be applied to the module',
                'sqlsrv',
            ],
        ];
    }

    public function handle()
    {
        if (!in_array($this->option('skeleton'), ['onion', 'mvc'])) {
            $this->error('Skeleton type is not registered');
            return false;
        }
        if (!in_array($this->option('database'), ['sqlsrv', 'mysql', 'pgsql'])) {
            $this->error('Database driver is not registered');
            return false;
        }
        $this->createModuleConfig();
        $this->copySkeleton();
        $this->generateTemplate();
        $this->prepareProviders();
        return parent::handle();
    }

    private function createModuleConfig(): void
    {
        if (!$this->files->isFile(config_path('modules.php')))
            $this->files->copy(__DIR__ . '/../config/modules.php', config_path('modules.php'));

        $module_config = require config_path('modules.php');

        $module_config['modules'][Str::snake($this->argument('name'))] = [
            'module_class' => '\\' . $this->laravel->getNamespace() . 'Modules\\'. Str::studly($this->argument('name')) . '\\Module',
            'enabled' => true,
        ];

        ob_start();
        echo "<?php\n\n";
        echo "return [\n";
        echo "\n\t'default_module' => ";
        echo $module_config['default_module'] ? "'{$module_config['default_module']}'" : 'null';
        echo ",";
        echo "\n\t'modules' => [";
        foreach ($module_config['modules'] as $key => $value) {
            echo "\n\t\t'{$key}' => [";
                echo "\n\t\t\t'module_class' => '{$value['module_class']}',";
                echo "\n\t\t\t'enabled' => ";
                echo $value['enabled'] ? 'true' : 'false';
                echo ",";
            echo "\n\t\t],";
        }
        echo "\n\t],\n];";
        $output = ob_get_clean();

        $this->files->put(
            config_path('modules.php'), $output
        );
    }

    private function copySkeleton(): void
    {
        $skeleton_dir = __DIR__ . '/../Skeleton/' . Str::studly($this->option('skeleton'));
        $module_path = ModuleManager::path($this->argument('name'), '');
        foreach (scandir($skeleton_dir) as $dir) {
            if (in_array($dir, ['.', '..'])) continue;
            $source_dir = $skeleton_dir . '/' . $dir;
            $this->files->copyDirectory($source_dir, $module_path . '/' . $dir);
        }
    }

    private function generateTemplate(): void
    {
        $this->createRouteExample();
        $this->createControllerExample();
        $this->createLangExample();
        $this->createViewExample();
        $this->createBladeComponentExample();
        $this->createViewComponentExample();
    }

    private function createRouteExample(): void
    {

        $stub = $this->files->get(__DIR__ . '/../stubs/skeleton/routes/web.stub');

        $stub = str_replace(
            ['DummyNamespace'],
            $this->laravel->getNamespace() . 'Modules\\' . $this->argument('name') . '\Controllers\BaseController', $stub
        );

        $path = ModuleManager::path($this->argument('name'), 'routes/web.php');

        $this->files->put(
            $path, $stub
        );
    }

    private function createControllerExample(): void
    {

        $stub = $this->files->get(__DIR__ . '/../stubs/skeleton/controllers/BaseController.stub');

        $stub = str_replace(
            ['{{ module_name }}'],
            $this->argument('name'), $stub
        );

        $stub = str_replace(
            ['DummyNamespace'],
            $this->laravel->getNamespace() . 'Modules\\' . $this->argument('name') . '\Controllers', $stub
        );

        $path = ModuleManager::path($this->argument('name'), 'Controllers/BaseController.php');

        $this->files->put(
            $path, $stub
        );
    }

    private function createLangExample(): void
    {
        $stub_en = $this->files->get(__DIR__ . '/../stubs/skeleton/resources/lang/en/general.stub');
        $stub_id = $this->files->get(__DIR__ . '/../stubs/skeleton/resources/lang/id/general.stub');

        $stub_en = str_replace(
            ['{{ module_name }}'],
            $this->argument('name'), $stub_en
        );
        $stub_id = str_replace(
            ['{{ module_name }}'],
            $this->argument('name'), $stub_id
        );

        $path_en = ModuleManager::path($this->argument('name'), 'resources/lang/en/general.php');
        $path_id = ModuleManager::path($this->argument('name'), 'resources/lang/id/general.php');

        $this->files->put(
            $path_en, $stub_en
        );
        $this->files->put(
            $path_id, $stub_id
        );
    }

    private function createViewExample(): void
    {
        $stub = $this->files->get(__DIR__ . '/../stubs/skeleton/resources/views/welcome.blade.stub');

        $stub = str_replace(
            ['{{ module_name }}'],
            $this->argument('name'), $stub
        );

        $path = ModuleManager::path($this->argument('name'), 'resources/views/welcome.blade.php');

        $this->files->put(
            $path, $stub
        );
    }

    private function createBladeComponentExample(): void
    {
        $stub = $this->files->get(__DIR__ . '/../stubs/skeleton/components/Alert.stub');

        $stub = str_replace(
            ['{{ module_name }}'],
            $this->argument('name'), $stub
        );

        $stub = str_replace(
            ['DummyNamespace'],
            $this->laravel->getNamespace() . 'Modules\\' . $this->argument('name') . '\Components', $stub
        );

        $path = ModuleManager::path($this->argument('name'), 'Components/Alert.php');

        $this->files->put(
            $path, $stub
        );
    }

    private function createViewComponentExample(): void
    {
        $stub = $this->files->get(__DIR__ . '/../stubs/skeleton/resources/views/components/alert.blade.stub');

        $stub = str_replace(
            ['{{ module_name }}'],
            $this->argument('name'), $stub
        );

        $path = ModuleManager::path($this->argument('name'), 'resources/views/components/alert.blade.php');

        $this->files->put(
            $path, $stub
        );
    }

    private function prepareProviders(): void
    {
        $this->call(
            'module:provide-route',
            [
                'name' => $this->argument('name'),
                '--skeleton' => $this->option('skeleton'),
            ]
        );
        $this->call(
            'module:provide-database',
            [
                'name' => $this->argument('name'),
                '--database' => $this->option('database'),
            ]
        );
        $this->call(
            'module:provide-view',
            [
                'name' => $this->argument('name'),
                '--skeleton' => $this->option('skeleton'),
            ]
        );
        $this->call(
            'module:provide-lang',
            [
                'name' => $this->argument('name'),
                '--skeleton' => $this->option('skeleton'),
            ]
        );
        $this->call(
            'module:provide-blade',
            [
                'name' => $this->argument('name'),
                '--skeleton' => $this->option('skeleton'),
            ]
        );
        $this->call(
            'module:provide-dependency',
            [
                'name' => $this->argument('name'),
            ]
        );
        $this->call(
            'module:provide-event',
            [
                'name' => $this->argument('name'),
                '--skeleton' => $this->option('skeleton'),
            ]
        );
        $this->call(
            'module:provide-messaging',
            [
                'name' => $this->argument('name'),
            ]
        );
    }
}