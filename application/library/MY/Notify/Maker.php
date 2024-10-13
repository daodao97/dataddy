<?php
namespace MY;

class Notify_Maker
{
    public static function make($conf = [])
    {
        if (!isset($conf['type'])) {
            return null;
        }
        switch ($conf['type']) {
            case 'lark':
                return new Notify_Lark($conf);
            default:
                return null;
        }
    }
}