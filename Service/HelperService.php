<?php


namespace Conduction\CommonGroundBundle\Service;


class HelperService
{
    public static function findOverlap(string $string1, string $string2): array
    {
        $results = [];
        $stringLength1 = strlen($string1);
        $stringLength2 = strlen($string2);

        $maximum = $stringLength1>$stringLength2?$stringLength2:$stringLength1;

        for ($iterator = 0; $iterator <= $maximum; $iterator++){
            $subString1 = substr($string1, -$iterator);
            $subString2 = substr($string2, 0, $iterator);
            if($subString1 == $subString2){
                $results[] = $subString1;
            }
        }
        var_Dump($results);
        return $results;
    }

    public static function replaceOverlap(string $string1, string $string2): ?string
    {
        if($overlap = HelperService::findOverlap($string1, $string2)){
            $overlap = $overlap[count($overlap)-1];
            $string1 = substr($string1, 0, -strlen($overlap));
            $string2 = substr($string2, strlen($overlap));

        } else {
            $overlap = '';
        }
        return $string1.$overlap.$string2;
    }

    public static function removeOverlap(string $string1, string $string2): ?string
    {
        if($overlap = HelperService::findOverlap($string1, $string2)){
            $overlap = $overlap[count($overlap)-1];
            $string1 = substr($string1, 0, -strlen($overlap));
        }
        return $string1;
    }
}