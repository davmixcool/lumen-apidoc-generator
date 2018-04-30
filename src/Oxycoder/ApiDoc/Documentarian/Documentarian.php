<?php

namespace Oxycoder\ApiDoc\Documentarian;

use Mni\FrontYAML\Parser;

/**
 * Class Documentarian
 * @package Mpociot\Documentarian
 */
class Documentarian
{

    /**
     * Return a fully qualified path to a given file.
     *
     * @param string $path
     *
     * @return string
     */
    private function resource_path($path = '')
    {
        return app()->basePath().'/resources'.($path ? '/'.$path : $path);
    }

    /**
     * Returns a config value
     *
     * @param string $key
     * @return mixed
     */
    public function config($folder, $key = null)
    {
        $config = include($folder . '/source/config.php');

        return is_null($key) ? $config : array_get($config, $key);
    }

    /**
     * Create a new API documentation folder and copy all needed files/stubs
     *
     * @param $folder
     */
    public function create($folder)
    {
        if(file_exists(resource_path('views/vendor/apidoc/assets'))) {
            $assetsDir = resource_path('views/vendor/apidoc/assets');
        } else {
            $assetsDir = __DIR__ . '/../../../resources/assets';
        }

        $folder = $folder . '/source';
        if (!is_dir($folder)) {
            mkdir($folder, 0777, true);
            mkdir($folder . '/../css');
            mkdir($folder . '/../js');
            mkdir($folder . '/includes');
            mkdir($folder . '/assets');
        }

        // copy stub files
        copy($assetsDir . '/stubs/index.md', $folder . '/index.md');
        copy($assetsDir . '/stubs/gitignore.stub', $folder . '/.gitignore');
        copy($assetsDir . '/stubs/includes/_errors.md', $folder . '/includes/_errors.md');
        copy($assetsDir . '/stubs/package.json', $folder . '/package.json');
        copy($assetsDir . '/stubs/gulpfile.js', $folder . '/gulpfile.js');
        copy($assetsDir . '/stubs/config.php', $folder . '/config.php');
        copy($assetsDir . '/stubs/js/all.js', $folder . '/../js/all.js');
        copy($assetsDir . '/stubs/css/style.css', $folder . '/../css/style.css');

        // copy resources
        rcopy($assetsDir . '/images/', $folder . '/assets/images');
        rcopy($assetsDir . '/js/', $folder . '/assets/js');
        rcopy($assetsDir . '/stylus/', $folder . '/assets/stylus');
    }

    /**
     * Generate the API documentation using the markdown and include files
     *
     * @param $folder
     * @return false|null
     */
    public function generate($folder)
    {
        $source_dir = $folder . '/source';

        if (!is_dir($source_dir)) {
            return false;
        }

        $parser = new Parser();

        $document = $parser->parse(file_get_contents($source_dir . '/index.md'));

        $frontmatter = $document->getYAML();
        $html = $document->getContent();

        // Parse and include optional include markdown files
        if (isset($frontmatter['includes'])) {
            foreach ($frontmatter['includes'] as $include) {
                if (file_exists($include_file = $source_dir . '/includes/_' . $include . '.md')) {
                    $document = $parser->parse(file_get_contents($include_file));
                    $html .= $document->getContent();
                }
            }
        }

        $output = view('apidoc::index', [
            'page' => $frontmatter,
            'content' => $html
        ])->render();

        file_put_contents($folder . '/index.html', $output);

        // Copy assets
        rcopy($source_dir . '/assets/images/', $folder . '/images');
        rcopy($source_dir . '/assets/stylus/fonts/', $folder . '/css/fonts');
    }
}
