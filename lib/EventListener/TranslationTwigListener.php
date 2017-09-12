<?php
declare(strict_types=1);
/*
 * @package    agitation/page-bundle
 * @link       http://github.com/agitation/page-bundle
 * @author     Alexander Günsche
 * @license    http://opensource.org/licenses/MIT
 */

namespace Agit\PageBundle\EventListener;

use Agit\BaseBundle\Service\FileCollector;
use Agit\IntlBundle\Event\BundleTranslationFilesEvent;
use Agit\PageBundle\TwigMeta\PageConfigExtractorTrait;
use Symfony\Component\Filesystem\Filesystem;
use Twig_Compiler;
use Twig_Environment;
use Twig_Node_Expression_Function;

class TranslationTwigListener
{
    use PageConfigExtractorTrait;

    protected $bundleTemplatesPath = 'Resources/views';

    private $fileCollector;

    private $twig;

    public function __construct(FileCollector $fileCollector, Twig_Environment $twig)
    {
        $this->fileCollector = $fileCollector;
        $this->twig = $twig;
    }

    public function onRegistration(BundleTranslationFilesEvent $event)
    {
        $bundleAlias = $event->getBundleAlias();
        $tplDir = $this->fileCollector->resolve($bundleAlias);
        $twigCache = $this->twig->getCache(false);

        // storing the old values to reset them when we’re done
        $actualCachePath = $this->twig->getCache();
        $actualAutoReload = $this->twig->isAutoReload();

        // create tmp cache path
        $filesystem = new Filesystem();
        $cachePath = $event->getCacheBasePath() . md5(__CLASS__);
        $filesystem->mkdir($cachePath);

        // setting temporary values
        $this->twig->enableAutoReload();
        $this->twig->setCache($cachePath);

        foreach ($this->fileCollector->collect($tplDir, 'twig') as $tplPath)
        {
            $config = $this->getConfigFromTemplate($tplPath);

            if ($config && is_array($config) && isset($config['name']) && $config['name'] instanceof Twig_Node_Expression_Function)
            {
                $compiler = new Twig_Compiler($this->twig);
                $config['name']->compile($compiler);

                $cacheFilePath = $twigCache->generateKey($tplPath, $this->twig->getTemplateClass($tplPath));
                $filesystem->dumpFile($cacheFilePath, '<?php ' . $compiler->getSource());
                $tplPathId = str_replace($tplDir, "@$bundleAlias/", $tplPath);
                $event->registerSourceFile($tplPathId, $cacheFilePath);
            }
        }

        // resetting original values
        $this->twig->setCache($actualCachePath);
        call_user_func([$this->twig, $actualAutoReload ? 'enableAutoReload' : 'disableAutoReload']);
    }

    // needed by PageConfigExtractorTrait
    protected function getTwig()
    {
        return $this->twig;
    }
}
