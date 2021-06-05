<?php

namespace Ebess\Console;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DeployServerCommand extends Command
{
    /**
     * code archive name
     */
    public const ARCHIVE_NAME = 'deploy.tar';
    /**
     * php deployment script name
     */
    public const SCRIPT_NAME = 'deploy.php';
    /**
     * @var string
     */
    protected $signature = 'deploy:server {server} {--refresh=0} {--debug=1}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deploy to server.';
    /**
     * @var mixed
     */
    protected $disk;
    /**
     * @var null|array
     */
    protected $config;
    /**
     * @var null|array
     */
    protected $includes;
    /**
     * @var null|array
     */
    protected $before;
    /**
     * @var null|array
     */
    protected $excludes;
    /**
     * @var mixed
     */
    protected $filesystem;
    /**
     * @var mixed
     */
    protected $configRepository;
    /**
     * @var mixed
     */
    protected $storage;
    /**
     * @var string[]
     */
    protected $remote;
    /**
     * @var string[]
     */
    protected $purgeExcludes;

    /**
     * @param $configRepository
     * @param $filesystem
     * @param $storage
     */
    public function __construct($configRepository, $filesystem, $storage)
    {
        parent::__construct();

        $this->configRepository = $configRepository;
        $this->filesystem       = $filesystem;
        $this->storage          = $storage;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // setup
        $this->setup();

        // run
        $this->info('------------------------------------------------------------------------');
        $this->info('deploy the project');
        $this->info('------------------------------------------------------------------------');
        $this->info("");
        $this->info("server:\t" . $this->argument('server'));
        $this->info("");

        $this->runBefore();
        $this->createArchive();
        $this->uploadFiles();
        $this->runDeploymentScript();
        $this->uploadSpecificFiles();
        $this->cleanUpAfter();

        $this->info("");
        $this->info('------------------------------------------------------------------------');
        $this->info('deployment done!');
        $this->info('------------------------------------------------------------------------');

        return 0;
    }

    /**
     * setup needed instances
     */
    private function setup()
    {
        $this->config        = $this->configRepository['servers'][$this->argument('server')];
        $this->before        = $this->configRepository['before'];
        $this->includes      = $this->configRepository['includes'];
        $this->excludes      = $this->configRepository['excludes'];
        $this->remote        = $this->configRepository['remote'];
        $this->purgeExcludes = $this->configRepository['purge_excludes'];
        $this->disk          = $this->storage->disk($this->config['disk']);
    }

    /**
     * @return void
     */
    private function runBefore(): void
    {
        $this->info('run commands before deployment.');

        foreach ($this->before as $cmd) {
            $this->info('- ' . $cmd);
            exec($cmd);
        }
    }

    /**
     * create zip archive
     * @return void
     */
    private function createArchive(): void
    {
        $this->info('building release zip.');

        // delete old archive
        if ($this->filesystem->isFile(storage_path('app/' . static::ARCHIVE_NAME))) {
            try {
                $this->filesystem->delete(storage_path('app/' . static::ARCHIVE_NAME));
            } catch (Exception $e) {
                $this->error('Ftp delete failed ' . $e->getMessage());
            }
        }

        // create new archive
        $this->createTarArchive();
    }

    /**
     * create the string to build the archive which will be uploaded
     *
     * @return void
     */
    private function createTarArchive(): void
    {
        $includes = implode(" ", $this->includes);

        $excludes = '';
        if (is_array($this->excludes)) {
            $excludes = implode(
                    " ",
                    array_map(
                            function ($v) {
                                return "--exclude=$v";
                            },
                            $this->excludes
                    )
            );
        }


        exec('tar -czf ' . static::ARCHIVE_NAME . ' ' . $includes . ' ' . $excludes);
        exec('mv ' . static::ARCHIVE_NAME . ' ' . storage_path('app'));
    }

    /**
     * @return void
     */
    private function uploadFiles(): void
    {
        $this->info('upload to server.');

        $files = [
                static::ARCHIVE_NAME            => $this->filesystem->get(storage_path('app/' . static::ARCHIVE_NAME)),
                'public/' . static::SCRIPT_NAME => $this->getDeploymentCode(),
        ];

        // upload
        foreach ($files as $dst => $content) {
            $this->disk->put($dst, $content);
        }
    }

    /**
     * return php deployment script code for unzipping archive and deleting old file
     *
     * @return string
     */
    private function getDeploymentCode()
    {
        $excludeFromPurge = implode(
                '|',
                array_map(
                        function ($path) {
                            return '/' . $path . '$';
                        },
                        $this->purgeExcludes
                )
        );

        if (!empty($excludeFromPurge)) {
            $excludeFromPurge = '|' . $excludeFromPurge;
        }

        return '
<?php

// vars
$debug = [];
$archive = \'' . static::ARCHIVE_NAME . '\';

// delete old files
exec(\'ls -d -1 $PWD/** | egrep -v "(\'.__FILE__.\'$' . $excludeFromPurge . ')" | xargs rm -rf\', $debug);
//exec(\'ls -d -1 $PWD/.** | egrep -v "(/..?$)" | xargs rm -rf\', $debug);

$exclude = \'(/\'.$archive.\'$|/public$|/storage$)\';
exec(\'ls -d -1 $PWD/../** | egrep -v "\'.$exclude.\'" | xargs rm -rf\', $debug);

// change dir
chdir(\'..\');

// unzip deployment archive
exec(\'tar -xf $PWD/\' . $archive, $debug);

// run migrations
exec(\'' . $this->config['php-cli'] . ' artisan migrate' . ($this->option(
                        'refresh'
                ) ? ':fresh --seed' : '') . ' --force\', $debug);

// run custom commands
' . implode('', $this->getRemoteCommands()) . '

// delete archive & self
exec(\'rm -rf $PWD/\' . $archive, $debug);

// output
echo json_encode($debug);
';
    }

    /**
     * generates the php string for remote execution of commands
     *
     * @return string[]
     */
    private function getRemoteCommands()
    {
        return array_map(
                function ($command) {
                    return 'exec(\'' . str_replace("'", "\\'", $command) . '\', $debug);';
                },
                $this->remote
        );
    }

    /**
     * @return void
     */
    private function runDeploymentScript(): void
    {
        $this->info('run deployment on server.');

        // call the deployment script
        $response = Http::get(
                $this->config['deploy-url'] . '/' . static::SCRIPT_NAME . '?archive=' . static::ARCHIVE_NAME
        );

        if ($this->option('debug')) {
            $this->info("\t- " . print_r($response->json(), true));
        }


        // delete the script itself
        try {
            $this->disk->delete('/public/' . static::SCRIPT_NAME);
        } catch (Exception $e) {
            // Can't delete the script. Empty it to avoid issues
            $this->disk->put('/public/' . static::SCRIPT_NAME, 'All done');
            $this->error('Ftp delete failed ' . $e->getMessage());
        }
    }

    /**
     * upload config for given stage
     */
    private function uploadSpecificFiles()
    {
        $this->info('upload defined files.');

        foreach ($this->config['uploads'] as $src => $dst) {
            $this->disk->put($dst, $this->filesystem->get($src));
        }
    }

    /**
     * clean up after completion of the uploading
     */
    private function cleanUpAfter()
    {
        $this->info('clean up after uploading.');

        // delete created archive
        $this->filesystem->delete(storage_path('app/' . static::ARCHIVE_NAME));
    }
}
