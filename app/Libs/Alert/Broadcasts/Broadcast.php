<?php

namespace Coyote\Alert\Broadcasts;

/**
 * Class Broadcast
 * @package Coyote\Alert\Broadcasts
 */
abstract class Broadcast
{
    /**
     * @param array $data
     * @param $content
     * @return mixed
     */
    protected function parse(array $data, $content)
    {
        $template = [];

        foreach ($data as $key => $value) {
            $template['{' . $key . '}'] = $value;
        }
        return str_ireplace(array_keys($template), array_values($template), $content);
    }
}
