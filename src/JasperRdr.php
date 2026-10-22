<?php

namespace Eltonwebnet\JasperRdr;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Renderiza relatórios Jasper localmente (via JAR Java embutido), usando um
 * datasource JSON — sem depender de um JasperReports Server.
 */
class JasperRdr
{
    /**
     * @param  string  $dados       JSON com os dados do relatório (datasource).
     * @param  string  $template    Caminho absoluto do .jrxml/.jasper.
     * @param  array<string,mixed>  $parameters  Parâmetros do relatório.
     * @param  string  $tipo        'pdf' (padrão) ou 'xlsx'.
     * @return array{json:string,report:string,extension:string,uuid:string,hasError:bool,message:array<int,string>}
     */
    public static function render(string $dados, string $template, array $parameters = [], string $tipo = 'pdf'): array
    {
        $uuid = Str::uuid()->toString();
        $extension = $tipo === 'pdf' ? 'pdf' : 'xlsx';

        $storageDir = Storage::disk('local')->path('relatorios');
        if (! is_dir($storageDir)) {
            @mkdir($storageDir, 0775, true);
        }
        $jsonPath = "{$storageDir}/{$uuid}.json";
        $reportPath = "{$storageDir}/{$uuid}.{$extension}";

        $jar = __DIR__.'/jasper-rdr-compiler.jar';

        // Validações antecipadas, com mensagens claras (evita o "file not found" obscuro).
        if (($erro = self::validar($template, $jar)) !== null) {
            Log::error('[JasperRdr] '.$erro);

            return self::resultado($jsonPath, $reportPath, $extension, $uuid, true, [$erro]);
        }

        Storage::disk('local')->put("relatorios/{$uuid}.json", $dados);

        // Parâmetros como JSON limpo (a versão antiga fazia um quoting manual que quebrava o JSON).
        $paramsJson = json_encode(
            array_map(static fn ($v) => (string) $v, $parameters),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        // Process com argumentos em array = escaping automático e seguro (sem passar pelo shell),
        // o que corrige os erros com espaços/aspas nos caminhos e nos parâmetros.
        $process = new Process([
            self::java(),
            '-jar', $jar,
            $template,
            $tipo,
            $uuid,
            $storageDir,
            $jsonPath,
            $paramsJson,
        ]);
        $process->setTimeout(120);

        try {
            $process->run();
        } catch (\Throwable $e) {
            Log::error('[JasperRdr] '.$e->getMessage());

            return self::resultado($jsonPath, $reportPath, $extension, $uuid, true, [$e->getMessage()]);
        }

        $saida = preg_split('/\r\n|\r|\n/', trim($process->getOutput()."\n".$process->getErrorOutput())) ?: [];
        $saida = array_values(array_filter($saida, static fn ($l) => $l !== ''));

        // Sucesso só se o processo retornou 0 E o arquivo foi realmente gerado.
        $hasError = ! $process->isSuccessful() || ! is_file($reportPath);
        if ($hasError) {
            Log::error('[JasperRdr] Falha ao gerar o relatório', ['saida' => $saida]);
        } else {
            @unlink($jsonPath);
        }

        return self::resultado($jsonPath, $reportPath, $extension, $uuid, $hasError, $saida);
    }

    private static function validar(string $template, string $jar): ?string
    {
        if (! is_file($template)) {
            return "Template não encontrado: {$template}";
        }
        if (! is_file($jar)) {
            return "Compilador (JAR) não encontrado: {$jar}";
        }
        if (self::java() === null) {
            return 'Java (JRE) não encontrado no PATH. Instale o Java para gerar relatórios.';
        }

        return null;
    }

    private static function java(): ?string
    {
        static $java = null;
        if ($java === null) {
            $java = (new ExecutableFinder())->find('java') ?: false;
        }

        return $java ?: null;
    }

    /**
     * @param  array<int,string>  $message
     * @return array{json:string,report:string,extension:string,uuid:string,hasError:bool,message:array<int,string>}
     */
    private static function resultado(string $json, string $report, string $ext, string $uuid, bool $hasError, array $message): array
    {
        return [
            'json' => $json,
            'report' => $report,
            'extension' => $ext,
            'uuid' => $uuid,
            'hasError' => $hasError,
            'message' => $message,
        ];
    }
}
