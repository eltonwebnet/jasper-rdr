<?php

namespace Eltonwebnet\JasperRdr;

use File;
use Illuminate\Support\Facades\Storage;
use Str;

class JasperRdr {
    public static function render(String $dados, String $template, Array $parameters = [], String $tipo = 'pdf'): array {

        $uuid = Str::uuid()->toString();
        $new_parameters = [];
        foreach ($parameters as $key => $value) {
            $new_key = "\"".$key."\"";
            $new_parameters[$new_key] = "\"".$value."\"";
        }
        $new_parameters_json = json_encode($new_parameters);
        $storage_full_path = Storage::disk("local")->path("relatorios");
        $json_full_path = "$storage_full_path\\$uuid.json";
        $report_file_full_path = "$storage_full_path\\$uuid" . ($tipo == "pdf" ? ".pdf" : ".xlsx");

        Storage::put("relatorios\\$uuid.json", $dados);
        dd(dirname(__FILE__));
        $render_path = "jasper-rdr-compiler.jar";
        exec('java -jar ' .$render_path. " $template $tipo $uuid $storage_full_path $json_full_path  $new_parameters_json 2>&1",$output, $result);
        $hasError = false;
        if ($result != 0) {
            $hasError = true;
        }
        if (!$hasError) {
            File::delete($json_full_path);
        }
        return [
            "json" => $json_full_path,
            "report" => $report_file_full_path,
            "extension" => $tipo == "pdf" ? "pdf" : "xlsx",
            "uuid" => $uuid,
            "hasError" => $hasError,
            "message" => $output
        ];
    }
}
