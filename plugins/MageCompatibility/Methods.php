<?php
namespace MageCompatibility;

class Methods extends Tags
{
    protected $data = array();

    public function add(Method $method)
    {
        $this->data[] = $method;
    }

    public function count()
    {
        return count($this->data);
    }
}
