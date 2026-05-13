<?php

namespace App\Services;

use App\Shared\Enums\InvoiceType;
use Exception;
use Illuminate\Support\Facades\Log;

require_once __DIR__ . '/AFIP/Afip.php';

class ArcaService
{
    private $afip;
    private string $cuit;
    private bool $production;
    private bool $mockMode;
    private int $puntoVenta;

    public function __construct()
    {
        $this->cuit = config('afip.cuit', '20000000001');
        $this->production = config('afip.production', false);
        $this->mockMode = config('afip.mock_arca', true);
        $this->puntoVenta = (int) config('afip.punto_venta', 5);

        if (! $this->mockMode) {
            try {
                $this->afip = new \Afip([
                    'CUIT' => $this->cuit,
                    'production' => $this->production,
                    'cert' => 'cert',
                    'key' => 'key',
                    'passphrase' => config('afip.passphrase', 'xxxxx'),
                    'res_folder' => __DIR__ . '/AFIP/Afip_res/',
                    'ta_folder' => __DIR__ . '/AFIP/Afip_res/',
                ]);
            } catch (Exception $e) {
                Log::error('Error inicializando AFIP: ' . $e->getMessage());

                throw $e;
            }
        }
    }

    private function generarDatosMock(): array
    {
        $cae = str_pad((string) rand(10000000000000, 99999999999999), 14, '0', STR_PAD_LEFT);
        $dias = rand(7, 14);
        $vencimiento = date('Ymd', strtotime("+{$dias} days"));

        return ['cae' => $cae, 'vencimiento' => $vencimiento];
    }

    public function getLastVoucher(int $tipoComprobante): int
    {
        if ($this->mockMode) {
            return rand(1, 10000);
        }

        return $this->getAfip()->ElectronicBilling->GetLastVoucher($this->puntoVenta, $tipoComprobante);
    }

    public function emitirComprobante(array $datos): array
    {
        try {
            $tipoComprobante = (int) $datos['tipo_comprobante'];
            $ultimoNumero = $this->getLastVoucher($tipoComprobante);
            $numero = $ultimoNumero + 1;

            $total = round((float) $datos['importe_total'], 2);
            $neto = round((float) $datos['importe_neto'], 2);
            $iva = round((float) $datos['importe_iva'], 2);

            $fecha = $datos['fecha'] ?? date('Y-m-d');
            $fechaFormateada = (int) str_replace('-', '', $fecha);

            $docTipo = (int) ($datos['doc_tipo'] ?? 99);
            $docNro = (int) ($datos['doc_numero'] ?? 0);

            $invoiceType = InvoiceType::tryFrom($tipoComprobante);
            $ivaId = 5; // 21% default
            $ivaRate = (float) ($datos['iva_rate'] ?? 21);
            if ($ivaRate == 10.5) {
                $ivaId = 4;
            }
            if ($ivaRate == 27) {
                $ivaId = 6;
            }

            $data = [
                'CantReg' => 1,
                'PtoVta' => $this->puntoVenta,
                'CbteTipo' => $tipoComprobante,
                'Concepto' => (int) ($datos['concepto'] ?? 2),
                'DocTipo' => $docTipo,
                'DocNro' => $docNro,
                'CbteDesde' => $numero,
                'CbteHasta' => $numero,
                'CbteFch' => $fechaFormateada,
                'FchServDesde' => null,
                'FchServHasta' => null,
                'FchVtoPago' => null,
                'ImpTotal' => $total,
                'ImpTotConc' => 0,
                'ImpNeto' => $neto,
                'ImpOpEx' => 0,
                'ImpIVA' => $iva,
                'ImpTrib' => 0,
                'MonId' => 'PES',
                'MonCotiz' => 1,
                'Iva' => [
                    [
                        'Id' => $ivaId,
                        'BaseImp' => $neto,
                        'Importe' => $iva,
                    ],
                ],
            ];

            if ($this->mockMode) {
                $mock = $this->generarDatosMock();
                Log::info("ARCA MOCK: Emitiendo comprobante tipo {$tipoComprobante}");

                return [
                    'success' => true,
                    'cae' => $mock['cae'],
                    'cae_vencimiento' => $mock['vencimiento'],
                    'numero_comprobante' => $numero,
                    'punto_venta' => $this->puntoVenta,
                    'tipo_comprobante' => $tipoComprobante,
                ];
            }

            $resultado = $this->getAfip()->ElectronicBilling->CreateVoucher($data);

            return [
                'success' => true,
                'cae' => $resultado['CAE'],
                'cae_vencimiento' => str_replace('-', '', $resultado['CAEFchVto']),
                'numero_comprobante' => $numero,
                'punto_venta' => $this->puntoVenta,
                'tipo_comprobante' => $tipoComprobante,
            ];
        } catch (Exception $e) {
            Log::error('Error emitiendo comprobante ARCA: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function consultarContribuyente(string $cuit): ?array
    {
        try {
            $cuitLimpio = preg_replace('/[^0-9]/', '', $cuit);
            if (strlen($cuitLimpio) < 11) {
                return null;
            }

            if ($this->mockMode) {
                return [
                    'razonSocial' => 'CONTRIBUYENTE MOCK ' . $cuitLimpio,
                    'tipoResponsable' => 'IVA Responsable Inscripto',
                    'domicilio' => 'Av. Ejemplo 1234, CABA, Buenos Aires',
                ];
            }

            $persona = $this->getAfip()->RegisterScopeThirteen->GetTaxpayerDetails((int) $cuitLimpio);
            if (! $persona) {
                return null;
            }

            $razonSocial = $persona->razonSocial
                ?? trim(($persona->apellido ?? '') . ', ' . ($persona->nombre ?? ''));

            $tipoResponsable = 'Consumidor Final';
            if (! empty($persona->impuestos)) {
                $impuestos = is_array($persona->impuestos) ? $persona->impuestos : [$persona->impuestos];
                foreach ($impuestos as $imp) {
                    $idImp = (int) ($imp->idImpuesto ?? 0);
                    if (strtoupper($imp->estado ?? '') !== 'ACTIVO') {
                        continue;
                    }
                    if ($idImp === 30) {
                        $tipoResponsable = 'IVA Responsable Inscripto';

                        break;
                    }
                    if ($idImp === 32) {
                        $tipoResponsable = 'IVA Exento';

                        break;
                    }
                    if ($idImp === 20) {
                        $tipoResponsable = 'Monotributista';

                        break;
                    }
                }
            }

            $domicilio = null;
            if (! empty($persona->domicilio)) {
                $domicilios = is_array($persona->domicilio) ? $persona->domicilio : [$persona->domicilio];
                $dom = null;
                foreach ($domicilios as $d) {
                    if (strtoupper($d->tipoDomicilio ?? '') === 'FISCAL') {
                        $dom = $d;

                        break;
                    }
                }
                $dom ??= $domicilios[0] ?? null;
                if ($dom) {
                    $dir = $dom->direccion ?? trim(($dom->calle ?? '') . ' ' . ($dom->numero ?? ''));
                    $partes = array_filter([$dir, $dom->localidad ?? null, $dom->descripcionProvincia ?? null, $dom->codigoPostal ?? null]);
                    $domicilio = implode(', ', $partes);
                }
            }

            return compact('razonSocial', 'tipoResponsable', 'domicilio');
        } catch (Exception $e) {
            Log::warning('Error consultando padrón ARCA: ' . $e->getMessage());

            return null;
        }
    }

    public function isMockMode(): bool
    {
        return $this->mockMode;
    }

    private function getAfip(): \Afip
    {
        if (! $this->afip) {
            $this->afip = new \Afip([
                'CUIT' => $this->cuit,
                'production' => $this->production,
                'cert' => 'cert',
                'key' => 'key',
                'passphrase' => config('afip.passphrase', 'xxxxx'),
                'res_folder' => __DIR__ . '/AFIP/Afip_res/',
                'ta_folder' => __DIR__ . '/AFIP/Afip_res/',
            ]);
        }

        return $this->afip;
    }
}
