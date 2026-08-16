<?php

namespace Database\Seeders;

use App\Shared\Models\ChatbotFaq;
use Illuminate\Database\Seeder;

/**
 * RC-490 — Preguntas frecuentes iniciales del chatbot.
 *
 * Se cargan con updateOrCreate por pregunta, así que volver a correr el seeder
 * refresca los textos sin duplicar ni pisar las que el admin haya agregado.
 */
class ChatbotFaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            [
                'question' => '¿Cómo sigo mi envío?',
                'answer' => 'Escribime el número de envío (por ejemplo: 53620) y te digo en qué estado está. '
                    . 'También podés consultarlo desde la sección de seguimiento de la web.',
                'keywords' => 'seguir,seguimiento,rastrear,tracking,donde esta,dónde está,estado de mi envio,ubicacion',
                'category' => 'envios',
                'sort_order' => 1,
            ],
            [
                'question' => '¿Cuánto tarda una entrega?',
                'answer' => 'Los envíos entre localidades cercanas suelen entregarse dentro de las 24 a 48 horas hábiles. '
                    . 'Si el destino es más lejano puede demorar hasta 72 horas hábiles.',
                'keywords' => 'cuanto tarda,cuánto tarda,demora,tiempo de entrega,cuando llega,cuándo llega,plazo',
                'category' => 'envios',
                'sort_order' => 2,
            ],
            [
                'question' => '¿Cuánto sale un envío?',
                'answer' => 'El precio depende del origen, el destino y la cantidad de bultos (chicos o grandes). '
                    . 'Escribinos el recorrido y la cantidad de bultos y te pasamos la cotización exacta.',
                'keywords' => 'precio,cuanto sale,cuánto sale,cuanto cuesta,tarifa,costo,cotizacion,cotización,valor',
                'category' => 'precios',
                'sort_order' => 3,
            ],
            [
                'question' => '¿Qué formas de pago aceptan?',
                'answer' => 'Aceptamos efectivo, transferencia, cheque y e-cheq. Los clientes con cuenta corriente '
                    . 'habilitada pueden pagar a cuenta y liquidar después.',
                'keywords' => 'pago,pagar,formas de pago,medios de pago,transferencia,efectivo,cheque,tarjeta',
                'category' => 'pagos',
                'sort_order' => 4,
            ],
            [
                'question' => '¿Cómo veo mi cuenta corriente?',
                'answer' => 'Desde el Portal del Cliente podés ver tus movimientos, el saldo actualizado y descargar '
                    . 'el detalle en PDF. Si todavía no tenés acceso, pedilo en el mostrador.',
                'keywords' => 'cuenta corriente,saldo,deuda,cuanto debo,cuánto debo,resumen,movimientos,estado de cuenta',
                'category' => 'cuenta',
                'sort_order' => 5,
            ],
            [
                'question' => '¿Cuáles son los horarios de atención?',
                'answer' => 'Atendemos de lunes a viernes de 8 a 12:30 y de 16 a 20, y los sábados de 8:30 a 12:30. '
                    . 'Los retiros del día se cierran a las 20.',
                'keywords' => 'horario,horarios,atencion,atención,abierto,cierran,abren,sabado,sábado',
                'category' => 'general',
                'sort_order' => 6,
            ],
            [
                'question' => '¿Dónde están ubicados?',
                'answer' => 'Tenemos sucursales en San Genaro, Las Rosas, Rosario, Díaz y Rafaela. '
                    . 'Decinos en qué localidad estás y te paso la dirección más cercana.',
                'keywords' => 'donde estan,dónde están,direccion,dirección,sucursal,sucursales,ubicacion,ubicación,local',
                'category' => 'general',
                'sort_order' => 7,
            ],
            [
                'question' => '¿Hacen retiro a domicilio?',
                'answer' => 'Sí. Coordinamos el retiro en el domicilio que nos indiques dentro de las localidades que '
                    . 'cubrimos. Pedilo por acá o por teléfono y te asignamos un cadete.',
                'keywords' => 'retiro,retirar,domicilio,buscar,pasan a buscar,recoleccion,recolección,puerta a puerta',
                'category' => 'envios',
                'sort_order' => 8,
            ],
            [
                'question' => '¿Qué pasa si no estoy cuando llega el cadete?',
                'answer' => 'El cadete deja aviso y reprogramamos la entrega para el próximo recorrido. '
                    . 'Si preferís, podés pasar a retirar el paquete por la sucursal de destino.',
                'keywords' => 'no estoy,ausente,nadie,reprogramar,no me encontraron,intento de entrega,volver a pasar',
                'category' => 'envios',
                'sort_order' => 9,
            ],
            [
                'question' => '¿Puedo asegurar el contenido del envío?',
                'answer' => 'Sí. Podés declarar el valor del contenido al momento de la carga y se aplica un porcentaje '
                    . 'sobre ese valor declarado. Consultanos el porcentaje vigente.',
                'keywords' => 'seguro,asegurar,valor declarado,declarar,cobertura,rotura,perdida,pérdida',
                'category' => 'envios',
                'sort_order' => 10,
            ],
            [
                'question' => '¿Qué cosas no puedo enviar?',
                'answer' => 'No transportamos dinero en efectivo, documentación original irreemplazable, animales vivos, '
                    . 'inflamables, explosivos ni sustancias peligrosas.',
                'keywords' => 'no puedo enviar,prohibido,restricciones,que no,peligroso,inflamable,animales,dinero',
                'category' => 'envios',
                'sort_order' => 11,
            ],
            [
                'question' => '¿Cómo pido una factura?',
                'answer' => 'Emitimos factura por cada operación. Si necesitás una en particular, decinos el número de '
                    . 'envío y la razón social a facturar, y te la enviamos por mail.',
                'keywords' => 'factura,facturacion,facturación,comprobante,recibo,iva,cae,fiscal',
                'category' => 'pagos',
                'sort_order' => 12,
            ],
            [
                'question' => 'Quiero hablar con una persona',
                'answer' => 'Te derivo con el equipo. Escribinos por WhatsApp o llamanos en horario de atención y te '
                    . 'atiende alguien del mostrador.',
                'keywords' => 'persona,humano,operador,hablar,asesor,atencion al cliente,reclamo,queja',
                'category' => 'general',
                'sort_order' => 13,
            ],
        ];

        foreach ($faqs as $faq) {
            ChatbotFaq::updateOrCreate(
                ['question' => $faq['question']],
                $faq + ['is_active' => true]
            );
        }
    }
}
