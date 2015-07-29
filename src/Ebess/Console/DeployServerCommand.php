<?php namespace Ebess\Console;

use GuzzleHttp\Client;
use Illuminate\Console\Command;

class DeployServerCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'deploy:server {server}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deploy to server.';

    /**
     * @var mixed
     */
    protected $disk = null;

    /**
     * @var null|array
     */
    protected $config = null;

    /**
     * @var null|array
     */
    protected $includes = null;

    /**
     * @var null|array
     */
    protected $before = null;

    /**
     * @var null|array
     */
    protected $excludes = null;

    /**
     * @var mixed
     */
    protected $filesystem = null;

    /**
     * @var mixed
     */
    protected $configRepository = null;

	/**
	 * @var mixed
	 */
    protected $storage = null;

	/**
	 * code archive name
	 */
	const ARCHIVE_NAME = 'deploy.tar';

	/**
	 * php deployment script name
	 */
	const SCRIPT_NAME = 'deploy.php';

	/**
	 * @param $configRepository
	 * @param $filesystem
	 * @param $storage
	 */
    public function __construct($configRepository, $filesystem, $storage)
    {
        parent::__construct();

        $this->configRepository = $configRepository;
        $this->filesystem = $filesystem;
	    $this->storage = $storage;
    }

    /**
     * setup needed instances
     */
    private function setup()
    {
        $this->config = $this->configRepository['servers'][$this->argument('server')];
        $this->before = $this->configRepository['before'];
        $this->includes = $this->configRepository['includes'];
        $this->excludes = $this->configRepository['excludes'];
        $this->disk = $this->storage->disk($this->config['disk']);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
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

        $this->runGrunt();
        $this->createArchive();
        $this->uploadFiles();
        $this->runDeploymentScript();
        $this->uploadSpecificFiles();
        $this->cleanUpAfter();

        $this->info("");
        $this->info('------------------------------------------------------------------------');
        $this->info('deployment done!');
        $this->info('------------------------------------------------------------------------');
    }

    /**
     * @return $this
     */
    private function runGrunt()
    {
        $this->info('run commands before deloyment.');

        foreach ($this->before as $cmd) {
            $this->info('- ' . $cmd);
            exec($cmd);
        }

        return $this;
    }

    /**
     * @return $this
     */
    private function runDeploymentScript()
    {
        $this->info('run deployment on server.');

        // call the deployment script
        $client = new Client();
        $client->get($this->config['deploy-url'] . '/' . static::SCRIPT_NAME . '?archive=' . static::ARCHIVE_NAME);

        // delete the script itself
        $this->disk->delete('/public/' . static::SCRIPT_NAME);

        return $this;
    }

    /**
     * @return $this
     */
    private function uploadFiles()
    {
        $this->info('upload to server.');

        $files = [
            static::ARCHIVE_NAME => $this->filesystem->get(storage_path('app/' . static::ARCHIVE_NAME)),
            'public/' . static::SCRIPT_NAME => $this->getDeploymentCode()
        ];

        // upload
        foreach ($files as $dst => $content) {
            $this->disk->put($dst, $content);
        }

        return $this;
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
     * create zip archive
     * @return $this
     */
    private function createArchive()
    {
        $this->info('building release zip.');

        // delete old archive
        if ($this->filesystem->isFile(storage_path('app/' . static::ARCHIVE_NAME))) {
            $this->filesystem->delete(storage_path('app/' . static::ARCHIVE_NAME));
        }

        // create new archive
        $this->createTarArchive();

        return $this;
    }

    /**
     * create the string to build the archive which will be uploaded
     *
     * @return string
     */
    private function createTarArchive()
    {
        $includes = implode(" ", $this->includes);

        $excludes = '';
        if (is_array($this->excludes)) {
            $excludes = implode(" ", array_map(function($v) {
                return "--exclude=$v";
            }, $this->excludes));
        }


        exec('tar -czf ' . static::ARCHIVE_NAME . ' '. $includes . ' ' . $excludes);
        exec('mv ' . static::ARCHIVE_NAME . ' ' . storage_path('app'));

        return $this;
    }

    /**
     * return php deployment script code for unzipping archive and deleting old file
     *
     * @return string
     */
    private function getDeploymentCode()
    {
        return '
			<?php
			
			// vars
			$archive = \''.static::ARCHIVE_NAME.'\';
			
			// delete old files
			exec(\'ls -d -1 $PWD/** | egrep -v "(\'.__FILE__.\'$)" | xargs rm -rf\');
			exec(\'ls -d -1 $PWD/.** | egrep -v "(/..?$)" | xargs rm -rf\');
			
			$exclude = \'(/\'.$archive.\'$|/public$)\';
			exec(\'ls -d -1 $PWD/../** | egrep -v "\'.$exclude.\'" | xargs rm -rf\');
			
			// unzip deployment archive
			exec(\'tar -xf $PWD/../\' . $archive . \' -C ..\');
			
			// delete archive & self
			exec(\'rm -rf $PWD/../\' . $archive);
		';

    }
}
