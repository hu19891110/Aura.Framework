<?php
namespace Aura\Framework\Web\Renderer;

use Aura\Framework\Inflect;
use Aura\View\TwoStep;
use Aura\Web\Renderer\AbstractRenderer;

class AuraViewTwoStep extends AbstractRenderer
{
    protected $twostep;
    
    protected $inflect;
    
    public function __construct(
        TwoStep $twostep,
        Inflect $inflect
    ) {
        $this->twostep = $twostep;
        $this->inflect = $inflect;
    }
    
    // allows us to call, e.g., $renderer->addInnerPath() to override stuff
    // in a seemingly-direct manner.
    public function __call($method, array $params)
    {
        return call_user_func_array([$this->twostep, $method], $params);
    }
    
    protected function prep()
    {
        // get all included files
        $includes = array_reverse(get_included_files());
        
        // get the controller class hierarchy stack
        $class = get_class($this->controller);
        $stack = class_parents($class);
        
        // drop Aura.Web and Aura.Framework
        array_pop($stack);
        array_pop($stack);
        
        // add the controller class itself
        array_unshift($stack, $class);
        
        // go through the hierarchy and look for each class file.
        // N.b.: this will not work if we concatenate all the classes into a
        // single file.
        foreach ($stack as $class) {
            $match = $this->inflect->classToFile($class);
            $len = strlen($match) * -1;
            foreach ($includes as $i => $include) {
                if (substr($include, $len) == $match) {
                    $dir = dirname($include);
                    $this->twostep->addInnerPath($dir . DIRECTORY_SEPARATOR . 'views');
                    $this->twostep->addOuterPath($dir . DIRECTORY_SEPARATOR . 'layouts');
                    unset($includes[$i]);
                    break;
                }
            }
        }
    }
    
    public function exec()
    {
        $this->twostep->setFormat($this->controller->getFormat());
        
        $response = $this->controller->getResponse();
        if (! $response->getContent()) {
            $this->twostep->setData((array) $this->controller->getData());
            $this->twostep->setAccept($this->controller->getContext()->getAccept());
            $this->twostep->setInnerView($this->controller->getView());
            $this->twostep->setOuterView($this->controller->getLayout());
            $response->setContent($this->twostep->render());
        }
        
        $response->setContentType($this->twostep->getContentType());
    }
}