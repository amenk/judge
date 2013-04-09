<?php
namespace Rewrites;

use Netresearch\Config;
use Netresearch\Logger;
use Netresearch\IssueHandler;
use Netresearch\Issue as Issue;
use Netresearch\PluginInterface as JudgePlugin;

/**
 * count Magento core rewrites
 */
class Rewrites implements JudgePlugin
{
    protected $config;
    protected $extensionPath;
    protected $rewrites=array();

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->name   = current(explode('\\', __CLASS__));
    }

    public function execute($extensionPath)
    {
        $score = 0;
        $settings = $this->config->plugins->{$this->name};
        $this->extensionPath = $extensionPath;

        exec(sprintf('find "%s" -name config.xml', $extensionPath), $configFiles);

        $rewriteCount = 0;
        $types = array('blocks', 'models', 'helpers');
        foreach ($configFiles as $configFile) {
            foreach ($types as $type) {
                $this->findRewrites($configFile, $type);
            }
        }

        if (count($this->rewrites) <= $settings->allowedRewrites->count) {
            $score += $settings->allowedRewrites->good;
        } elseif ($settings->maxRewrites->count < count($this->rewrites)) {
            $score += $settings->maxRewrites->good;
        } else {
            $score += $settings->maxRewrites->bad;
        }
        foreach ($this->rewrites as $rewrite) {
            list($type, $code) = explode('s:', $rewrite);
            if ($this->isCritical($rewrite)) {
                IssueHandler::addIssue(new Issue(
                        array(  "extension" =>  $extensionPath,
                                "checkname" => $this->name,
                                "type"      => 'critical_' . $type . '_rewrite',
                                "comment"   => $code,
                                "failed"    =>  true)));
                
                $score += $settings->critical->bad;
            } else {
                IssueHandler::addIssue(new Issue(
                        array(  "extension" =>  $extensionPath,
                                "checkname" => $this->name,
                                "type"      => $type . '_rewrite',
                                "comment"   => $code,
                                "failed"    =>  true)));
            }
        }

        Logger::setScore($extensionPath, $this->name, $score);
        return $score;
    }

    protected function findRewrites($configFile, $type)
    {
        $xpath = '/config/global/' . $type . '//rewrite/..';
        $config = simplexml_load_file($configFile);
        foreach ($config->xpath($xpath) as $moduleRewrites) {
            $module = $moduleRewrites->getName();
            foreach ($moduleRewrites->rewrite->children() as $path=>$class) {
                $this->rewrites[] = $type . ':' . $module . '/' . $path;
            }
        }
    }

    protected function isCritical($rewrite)
    {
        $critical = $this->config->plugins->{$this->name}->critical->toArray();
        list($type, $code) = explode(':', $rewrite);
        if (!isset($critical[$type]) || false == is_array($critical[$type])) {
            return false;
        }
        return in_array($code, $critical[$type]);
    }
}


