<?php

namespace Database\Seeders;

use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\InvoiceType;
use App\Shared\Enums\PaymentMethod;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\CommissionItem;
use App\Shared\Models\CurrentAccount;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Expense;
use App\Shared\Models\ExpenseCategory;
use App\Shared\Models\ExtraordinaryCommission;
use App\Shared\Models\FeedbackSurvey;
use App\Shared\Models\Franchise;
use App\Shared\Models\Income;
use App\Shared\Models\IncomeCategory;
use App\Shared\Models\Invoice;
use App\Shared\Models\Location;
use App\Shared\Models\MatrixReceivable;
use App\Shared\Models\Transport;
use App\Shared\Models\User;
use App\Shared\Models\WhatsAppCampaign;
use App\Shared\Models\WhatsAppCampaignMessage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class QASeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creando data QA completa...');

        // ── 1. Franquicias ──
        $franchiseA = Franchise::create([
            'name' => 'Franquicia Buenos Aires',
            'slug' => 'franquicia-bsas',
            'address' => 'Av. Corrientes 1234, CABA',
            'phone' => '11-4567-8900',
            'email' => 'bsas@ryr.com.ar',
            'commission_percentage_to_matrix' => 10,
            'status' => 'active',
            'contract_start_date' => '2026-01-01',
        ]);

        $franchiseB = Franchise::create([
            'name' => 'Franquicia Córdoba',
            'slug' => 'franquicia-cba',
            'address' => 'Av. Colón 567, Córdoba',
            'phone' => '351-456-7890',
            'email' => 'cordoba@ryr.com.ar',
            'commission_percentage_to_matrix' => 8.5,
            'status' => 'active',
            'contract_start_date' => '2026-02-01',
        ]);

        $this->command->info('  ✓ 2 franquicias creadas');

        // ── 2. Sucursales ──
        $branchCentral = Branch::create([
            'name' => 'Sucursal Central',
            'address' => 'Av. Corrientes 1234, CABA',
            'schedule' => 'Lun-Vie 8:00-18:00',
            'phone' => '11-4567-8900',
            'franchise_id' => $franchiseA->id,
        ]);

        $branchNorte = Branch::create([
            'name' => 'Sucursal Norte',
            'address' => 'Av. Cabildo 3456, CABA',
            'schedule' => 'Lun-Vie 9:00-17:00',
            'phone' => '11-4567-8901',
            'franchise_id' => $franchiseA->id,
        ]);

        $branchCordoba = Branch::create([
            'name' => 'Sucursal Córdoba Centro',
            'address' => 'Av. Colón 567, Córdoba',
            'schedule' => 'Lun-Sab 8:00-20:00',
            'phone' => '351-456-7890',
            'franchise_id' => $franchiseB->id,
        ]);

        $this->command->info('  ✓ 3 sucursales creadas');

        // ── 3. Usuarios (todos password: "password") ──
        $admin = User::create([
            'name' => 'Juan Admin',
            'email' => 'admin@ryr.com.ar',
            'password' => Hash::make('password'),
            'role' => 'administrador',
            'branch_id' => $branchCentral->id,
            'base_salary' => 500000,
            'contract_type' => 'fixed_salary',
        ]);

        $franchiseA->update(['owner_user_id' => $admin->id]);

        $adminFranquiciaA = User::create([
            'name' => 'Laura Franquicia BSAS',
            'email' => 'laura@ryr.com.ar',
            'password' => Hash::make('password'),
            'role' => 'admin_franquicia',
            'branch_id' => $branchCentral->id,
            'franchise_id' => $franchiseA->id,
            'base_salary' => 400000,
            'contract_type' => 'fixed_salary',
        ]);

        $adminFranquiciaB = User::create([
            'name' => 'Carlos Franquicia CBA',
            'email' => 'carlos@ryr.com.ar',
            'password' => Hash::make('password'),
            'role' => 'admin_franquicia',
            'branch_id' => $branchCordoba->id,
            'franchise_id' => $franchiseB->id,
            'base_salary' => 350000,
            'contract_type' => 'fixed_salary',
        ]);

        $mostrador = User::create([
            'name' => 'Ana Mostrador',
            'email' => 'mostrador@ryr.com.ar',
            'password' => Hash::make('password'),
            'role' => 'mostrador',
            'branch_id' => $branchCentral->id,
            'franchise_id' => $franchiseA->id,
            'base_salary' => 300000,
            'contract_type' => 'fixed_salary',
        ]);

        $cadete1 = User::create([
            'name' => 'Pedro Cadete',
            'email' => 'cadete@ryr.com.ar',
            'password' => Hash::make('password'),
            'role' => 'cadete',
            'branch_id' => $branchCentral->id,
            'franchise_id' => $franchiseA->id,
            'contract_type' => 'per_pickup',
            'payment_per_pickup' => 500,
        ]);

        $cadete2 = User::create([
            'name' => 'Diego Cadete Ext',
            'email' => 'cadete.ext@ryr.com.ar',
            'password' => Hash::make('password'),
            'role' => 'cadete_externo',
            'branch_id' => $branchNorte->id,
            'franchise_id' => $franchiseA->id,
            'contract_type' => 'per_pickup',
            'payment_per_pickup' => 450,
        ]);

        $cobrador = User::create([
            'name' => 'Mario Cobrador',
            'email' => 'cobrador@ryr.com.ar',
            'password' => Hash::make('password'),
            'role' => 'cobrador',
            'branch_id' => $branchCentral->id,
            'franchise_id' => $franchiseA->id,
            'base_salary' => 280000,
            'contract_type' => 'fixed_plus_commission',
            'commission_percentage' => 5,
        ]);

        $cadeteCba = User::create([
            'name' => 'Ramiro Cadete CBA',
            'email' => 'cadete.cba@ryr.com.ar',
            'password' => Hash::make('password'),
            'role' => 'cadete',
            'branch_id' => $branchCordoba->id,
            'franchise_id' => $franchiseB->id,
            'contract_type' => 'per_pickup',
            'payment_per_pickup' => 400,
        ]);

        $this->command->info('  ✓ 8 usuarios creados (password: "password")');

        // ── 4. Destinos y Tarifas ──
        $this->call(DestinationSeeder::class);
        $destinations = Destination::all();
        $this->command->info('  ✓ Destinos y tarifas creados');

        // ── 5. Locaciones ──
        $locations = collect();
        $locationData = [
            ['name' => 'Deposito Central', 'address' => 'Av. Juan de Garay 100', 'origin' => 'Buenos Aires', 'phone' => '11-4000-0001', 'schedule' => '8-18'],
            ['name' => 'Punto Retiro Belgrano', 'address' => 'Av. Cabildo 2100', 'origin' => 'Buenos Aires', 'phone' => '11-4000-0002', 'schedule' => '9-17'],
            ['name' => 'Deposito Córdoba', 'address' => 'Ruta 9 km 5', 'origin' => 'Córdoba', 'phone' => '351-400-0003', 'schedule' => '8-20'],
            ['name' => 'Punto Retiro Rosario', 'address' => 'Bv. Oroño 500', 'origin' => 'Rosario', 'phone' => '341-400-0004', 'schedule' => '9-18'],
        ];
        foreach ($locationData as $loc) {
            $locations->push(Location::create($loc));
        }
        $this->command->info('  ✓ 4 locaciones creadas');

        // ── 6. Transportes ──
        $transport1 = Transport::create([
            'plate' => 'AA-123-BB',
            'description' => 'Fiat Fiorino 2024',
            'phone' => '11-5000-0001',
            'insurance' => 'La Caja - Poliza 12345',
            'usage' => 'Reparto urbano CABA',
            'cadete_id' => $cadete1->id,
        ]);

        $transport2 = Transport::create([
            'plate' => 'CC-456-DD',
            'description' => 'Renault Kangoo 2023',
            'phone' => '11-5000-0002',
            'insurance' => 'Zurich - Poliza 67890',
            'usage' => 'Reparto zona norte',
            'cadete_id' => $cadete2->id,
        ]);

        $this->command->info('  ✓ 2 transportes creados');

        // ── 7. Categorías ──
        $this->call(ExpenseCategorySeeder::class);
        $this->call(IncomeCategorySeeder::class);
        $this->call(ExtraordinaryCommissionsSeeder::class);
        $expenseCategories = ExpenseCategory::all();
        $incomeCategories = IncomeCategory::all();
        $this->command->info('  ✓ Categorías de gastos/ingresos creadas');

        // ── 8. Clientes ──
        $customers = collect();
        $customerData = [
            ['dni' => 30111222, 'name' => 'Empresa', 'last_name' => 'MercadoLibre', 'mobile' => '1155001111', 'email' => 'envios@meli.com.ar', 'city' => 'Buenos Aires', 'is_premium' => true, 'iva_status' => 'always', 'franchise_id' => $franchiseA->id],
            ['dni' => 30222333, 'name' => 'Farmacia', 'last_name' => 'San Jorge', 'mobile' => '1155002222', 'email' => 'envios@sanjorge.com', 'city' => 'Buenos Aires', 'is_premium' => true, 'iva_status' => 'auto', 'franchise_id' => $franchiseA->id],
            ['dni' => 30333444, 'name' => 'Panadería', 'last_name' => 'Don Pedro', 'mobile' => '1155003333', 'email' => 'pedidos@donpedro.com', 'city' => 'Buenos Aires', 'is_premium' => false, 'iva_status' => 'auto', 'franchise_id' => $franchiseA->id],
            ['dni' => 30444555, 'name' => 'Estudio', 'last_name' => 'Contable López', 'mobile' => '1155004444', 'email' => 'info@estudiolopez.com', 'city' => 'Buenos Aires', 'is_premium' => false, 'iva_status' => 'exempt', 'franchise_id' => $franchiseA->id],
            ['dni' => 30555666, 'name' => 'María', 'last_name' => 'González', 'mobile' => '1155005555', 'email' => 'maria.gonzalez@gmail.com', 'city' => 'Buenos Aires', 'is_premium' => false, 'iva_status' => 'auto', 'franchise_id' => $franchiseA->id],
            ['dni' => 30666777, 'name' => 'Distribuidora', 'last_name' => 'Córdoba Sur', 'mobile' => '3515006666', 'email' => 'envios@cordobasur.com', 'city' => 'Córdoba', 'is_premium' => true, 'iva_status' => 'always', 'franchise_id' => $franchiseB->id],
            ['dni' => 30777888, 'name' => 'Roberto', 'last_name' => 'Fernández', 'mobile' => '3515007777', 'email' => 'roberto.fernandez@gmail.com', 'city' => 'Córdoba', 'is_premium' => false, 'iva_status' => 'auto', 'franchise_id' => $franchiseB->id],
            ['dni' => 30888999, 'name' => 'Tienda', 'last_name' => 'Online Express', 'mobile' => null, 'email' => 'ventas@onlineexpress.com', 'city' => 'Rosario', 'is_premium' => false, 'iva_status' => 'auto', 'franchise_id' => $franchiseA->id],
        ];

        foreach ($customerData as $cd) {
            $customers->push(Customer::create(array_merge($cd, [
                'address' => fake()->address(),
                'auto_calculate_iva' => true,
                'branch_id' => $cd['franchise_id'] === $franchiseA->id ? $branchCentral->id : $branchCordoba->id,
            ])));
        }

        $clienteUser = User::create([
            'name' => 'Cliente App',
            'email' => 'cliente@ryr.com.ar',
            'password' => Hash::make('password'),
            'role' => 'cliente',
            'branch_id' => $branchCentral->id,
        ]);
        $customers->first()->update(['user_id' => $clienteUser->id, 'internal_user_id' => $clienteUser->id]);

        $this->command->info('  ✓ 8 clientes creados (3 IVA status distintos, 1 sin móvil)');

        // ── 9. Comisiones en distintos estados y fechas ──
        $commissions = collect();
        $today = now();
        $statuses = [
            CommissionStatus::SOLICITUD_RECIBIDA,
            CommissionStatus::CADETE_ASIGNADO,
            CommissionStatus::EN_CAMINO_PLANTA,
            CommissionStatus::EN_PROCESO_ENTREGA,
            CommissionStatus::ENTREGADO,
            CommissionStatus::ENTREGADO,
            CommissionStatus::ENTREGADO,
            CommissionStatus::PAGO_VALIDACION,
            CommissionStatus::PAGO_VALIDACION,
            CommissionStatus::PAGO_CONFIRMADO,
            CommissionStatus::PAGO_CONFIRMADO,
            CommissionStatus::CANCELADO,
            CommissionStatus::PENDIENTE_PAGO,
            CommissionStatus::DISPONIBLE_RETIRO,
            CommissionStatus::EN_DEVOLUCION,
        ];

        $paymentMethods = [PaymentMethod::EFECTIVO, PaymentMethod::TRANSFERENCIA, PaymentMethod::CHEQUE, PaymentMethod::CUENTA_CORRIENTE];

        foreach ($statuses as $i => $status) {
            $isBsAs = $i < 12;
            $customer = $isBsAs ? $customers[$i % 5] : $customers[5 + ($i % 2)];
            $branch = $isBsAs ? $branchCentral : $branchCordoba;
            $franchise = $isBsAs ? $franchiseA : $franchiseB;
            $user = $isBsAs ? $admin : $adminFranquiciaB;
            $cadete = $isBsAs ? $cadete1 : $cadeteCba;
            $dest = $destinations->random();

            $total = fake()->randomFloat(2, 500, 15000);
            $ivaApplied = $customer->iva_status === 'always';
            $ivaAmount = $ivaApplied ? round($total - ($total / 1.21), 2) : 0;

            $daysAgo = max(0, 30 - ($i * 2));
            $date = $today->copy()->subDays($daysAgo);

            $commission = Commission::create([
                'client_id' => $customer->id,
                'destination_id' => $dest->id,
                'branch_id' => $branch->id,
                'user_id' => $user->id,
                'franchise_id' => $franchise->id,
                'date' => $date->format('Y-m-d'),
                'status' => $status->value,
                'payment_method' => $paymentMethods[array_rand($paymentMethods)]->value,
                'total' => $total,
                'iva_applied' => $ivaApplied,
                'iva_amount' => $ivaAmount,
                'cadete_id' => in_array($status, [CommissionStatus::SOLICITUD_RECIBIDA, CommissionStatus::CANCELADO]) ? null : $cadete->id,
                'origin_location_id' => $locations[0]->id,
                'destination_location_id' => $locations[1]->id,
            ]);

            // Items para cada comisión
            $itemCount = fake()->numberBetween(1, 3);
            for ($j = 0; $j < $itemCount; $j++) {
                $qty = fake()->numberBetween(1, 5);
                $price = fake()->randomFloat(2, 100, 3000);
                CommissionItem::create([
                    'commission_id' => $commission->id,
                    'type' => 'ORDINARIA',
                    'size' => fake()->randomElement(['CHICO', 'GRANDE']),
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'subtotal' => $qty * $price,
                    'detail' => fake()->words(3, true),
                ]);
            }

            $commissions->push($commission);
        }

        $this->command->info('  ✓ 15 comisiones creadas (variados estados, fechas, franquicias)');

        // ── 10. Cuenta Corriente (pagos y débitos) ──
        $caPayments = collect();

        // Pagos confirmados (facturables)
        $confirmedPayment1 = CurrentAccount::create([
            'customer_id' => $customers[0]->id,
            'type' => 'credit',
            'status' => 'OK',
            'amount' => 5000,
            'description' => 'Pago transferencia bancaria',
            'transaction_date' => $today->copy()->subDays(5)->format('Y-m-d'),
            'balance' => 5000,
            'payment_method' => 'transfer',
            'franchise_id' => $franchiseA->id,
            'verified_by_user_id' => $admin->id,
            'verified_at' => now(),
        ]);
        $caPayments->push($confirmedPayment1);

        $confirmedPayment2 = CurrentAccount::create([
            'customer_id' => $customers[1]->id,
            'type' => 'credit',
            'status' => 'OK',
            'amount' => 8500,
            'description' => 'Pago en efectivo sucursal',
            'transaction_date' => $today->copy()->subDays(3)->format('Y-m-d'),
            'balance' => 8500,
            'payment_method' => 'cash',
            'franchise_id' => $franchiseA->id,
            'verified_by_user_id' => $mostrador->id,
            'verified_at' => now(),
        ]);
        $caPayments->push($confirmedPayment2);

        // Pago pendiente
        CurrentAccount::create([
            'customer_id' => $customers[2]->id,
            'type' => 'credit',
            'status' => 'PENDIENTE',
            'amount' => 3000,
            'description' => 'Pago cheque por confirmar',
            'transaction_date' => $today->copy()->subDays(1)->format('Y-m-d'),
            'balance' => 3000,
            'payment_method' => 'check',
            'franchise_id' => $franchiseA->id,
        ]);

        // Débitos
        CurrentAccount::create([
            'customer_id' => $customers[0]->id,
            'type' => 'debit',
            'status' => 'OK',
            'amount' => 12000,
            'description' => 'Servicio de envío mensual',
            'transaction_date' => $today->copy()->subDays(10)->format('Y-m-d'),
            'balance' => -7000,
            'payment_method' => null,
            'franchise_id' => $franchiseA->id,
        ]);

        // Pago franquicia B
        $confirmedPaymentB = CurrentAccount::create([
            'customer_id' => $customers[5]->id,
            'type' => 'credit',
            'status' => 'OK',
            'amount' => 6000,
            'description' => 'Pago transferencia CBA',
            'transaction_date' => $today->copy()->subDays(2)->format('Y-m-d'),
            'balance' => 6000,
            'payment_method' => 'transfer',
            'franchise_id' => $franchiseB->id,
            'verified_by_user_id' => $adminFranquiciaB->id,
            'verified_at' => now(),
        ]);

        $this->command->info('  ✓ 5 movimientos de cuenta corriente (3 pagos OK, 1 pendiente, 1 débito)');

        // ── 11. Gastos ──
        foreach ([15, 10, 5, 2] as $daysAgo) {
            Expense::create([
                'transport_id' => $transport1->id,
                'expense_category_id' => $expenseCategories->random()->id,
                'user_id' => $admin->id,
                'date' => $today->copy()->subDays($daysAgo)->format('Y-m-d'),
                'detail' => fake()->sentence(4),
                'amount' => fake()->randomFloat(2, 500, 5000),
                'franchise_id' => $franchiseA->id,
            ]);
        }

        Expense::create([
            'transport_id' => $transport2->id,
            'expense_category_id' => $expenseCategories->first()->id,
            'user_id' => $adminFranquiciaB->id,
            'date' => $today->copy()->subDays(3)->format('Y-m-d'),
            'detail' => 'Combustible ruta Córdoba',
            'amount' => 15000,
            'franchise_id' => $franchiseB->id,
        ]);

        $this->command->info('  ✓ 5 gastos creados');

        // ── 12. Ingresos ──
        foreach ([20, 12, 6, 1] as $daysAgo) {
            Income::create([
                'income_category_id' => $incomeCategories->random()->id,
                'user_id' => $admin->id,
                'date' => $today->copy()->subDays($daysAgo)->format('Y-m-d'),
                'detail' => fake()->sentence(3),
                'amount' => fake()->randomFloat(2, 1000, 20000),
                'franchise_id' => $franchiseA->id,
            ]);
        }

        Income::create([
            'income_category_id' => $incomeCategories->first()->id,
            'user_id' => $adminFranquiciaB->id,
            'date' => $today->copy()->subDays(4)->format('Y-m-d'),
            'detail' => 'Ingreso franquicia CBA',
            'amount' => 25000,
            'franchise_id' => $franchiseB->id,
        ]);

        $this->command->info('  ✓ 5 ingresos creados');

        // ── 13. Matrix Receivables (cuentas por cobrar) ──
        $pagoValidacionComms = $commissions->filter(fn ($c) => $c->status->value === CommissionStatus::PAGO_VALIDACION->value);
        foreach ($pagoValidacionComms as $comm) {
            $franchise = Franchise::find($comm->franchise_id);
            if (!$franchise || $franchise->commission_percentage_to_matrix <= 0) continue;

            MatrixReceivable::create([
                'franchise_id' => $comm->franchise_id,
                'commission_id' => $comm->id,
                'amount' => round($comm->total * $franchise->commission_percentage_to_matrix / 100, 2),
                'percentage_applied' => $franchise->commission_percentage_to_matrix,
                'commission_total' => $comm->total,
                'status' => 'pending',
                'due_date' => $today->copy()->addDays(15)->format('Y-m-d'),
            ]);
        }

        // Receivable pagado (historial)
        MatrixReceivable::create([
            'franchise_id' => $franchiseA->id,
            'commission_id' => $commissions->where('status', CommissionStatus::PAGO_CONFIRMADO)->first()?->id,
            'amount' => 750,
            'percentage_applied' => 10,
            'commission_total' => 7500,
            'status' => 'paid',
            'paid_at' => $today->copy()->subDays(5),
            'payment_reference' => 'TRF-20260508-001',
            'due_date' => $today->copy()->subDays(10)->format('Y-m-d'),
        ]);

        $this->command->info('  ✓ Matrix receivables creados (pendientes + 1 pagado)');

        // ── 14. Campañas WhatsApp ──
        // Borrador
        WhatsAppCampaign::create([
            'name' => 'Promo Invierno 2026',
            'message_template' => 'Hola {nombre}! Aprovechá nuestras promos de invierno. 20% OFF en envíos a Córdoba. Consultanos!',
            'status' => 'draft',
            'segment_filters' => ['has_mobile' => true, 'city' => 'Buenos Aires'],
            'franchise_id' => $franchiseA->id,
            'created_by' => $admin->id,
        ]);

        // Completada con mensajes
        $campaignCompleted = WhatsAppCampaign::create([
            'name' => 'Bienvenida Nuevos Clientes',
            'message_template' => 'Hola {nombre}! Bienvenido a RYR Comisiones. Tu primera entrega tiene 10% de descuento.',
            'status' => 'completed',
            'segment_filters' => ['has_mobile' => true],
            'franchise_id' => $franchiseA->id,
            'created_by' => $admin->id,
            'total_recipients' => 5,
            'sent_count' => 4,
            'failed_count' => 1,
            'started_at' => $today->copy()->subDays(7),
            'completed_at' => $today->copy()->subDays(7)->addMinutes(3),
        ]);

        // Mensajes de la campaña completada
        foreach ($customers->take(5) as $i => $cust) {
            if (!$cust->mobile) continue;
            WhatsAppCampaignMessage::create([
                'campaign_id' => $campaignCompleted->id,
                'customer_id' => $cust->id,
                'phone' => $cust->mobile,
                'message_sent' => "Hola {$cust->name}! Bienvenido a RYR Comisiones. Tu primera entrega tiene 10% de descuento.",
                'status' => $i < 4 ? 'sent' : 'failed',
                'error_message' => $i >= 4 ? 'Número no registrado en WhatsApp' : null,
                'sent_at' => $i < 4 ? $today->copy()->subDays(7) : null,
            ]);
        }

        // Programada
        WhatsAppCampaign::create([
            'name' => 'Recordatorio Pago Pendiente',
            'message_template' => 'Hola {nombre}, te recordamos que tenés un saldo pendiente en tu cuenta corriente. Podés pagarlo por transferencia.',
            'status' => 'scheduled',
            'segment_filters' => ['has_mobile' => true, 'is_premium' => false],
            'franchise_id' => $franchiseA->id,
            'created_by' => $mostrador->id,
            'scheduled_at' => $today->copy()->addDays(3),
        ]);

        // Campaña franquicia B
        WhatsAppCampaign::create([
            'name' => 'Apertura Córdoba',
            'message_template' => 'Hola {nombre}! Ya estamos en Córdoba. Envíos con retiro en sucursal. Consultanos!',
            'status' => 'draft',
            'segment_filters' => ['has_mobile' => true, 'city' => 'Córdoba'],
            'franchise_id' => $franchiseB->id,
            'created_by' => $adminFranquiciaB->id,
        ]);

        $this->command->info('  ✓ 4 campañas WhatsApp (draft, completada, programada, otra franquicia)');

        // ── 15. Feedback Surveys ──
        $entregadas = $commissions->filter(fn ($c) => $c->status->value === CommissionStatus::ENTREGADO->value);

        // Encuestas respondidas
        foreach ($entregadas->take(2) as $i => $comm) {
            FeedbackSurvey::create([
                'commission_id' => $comm->id,
                'customer_id' => $comm->client_id,
                'franchise_id' => $comm->franchise_id,
                'rating' => $i === 0 ? 5 : 4,
                'comment' => $i === 0 ? 'Excelente servicio, muy rápido!' : 'Bien, llegó en tiempo y forma.',
                'status' => 'responded',
                'token' => Str::random(64),
                'sent_at' => $today->copy()->subDays(10),
                'responded_at' => $today->copy()->subDays(9),
            ]);
        }

        // Encuesta pendiente (para probar respuesta pública)
        $pendingComm = $entregadas->last();
        if ($pendingComm) {
            $pendingSurvey = FeedbackSurvey::create([
                'commission_id' => $pendingComm->id,
                'customer_id' => $pendingComm->client_id,
                'franchise_id' => $pendingComm->franchise_id,
                'status' => 'pending',
                'token' => 'qa-test-token-feedback-12345',
                'sent_at' => $today->copy()->subDays(1),
            ]);
            $this->command->info("  → Token feedback pendiente: qa-test-token-feedback-12345");
            $this->command->info("    URL: /feedback/qa-test-token-feedback-12345");
        }

        $this->command->info('  ✓ 3 encuestas feedback (2 respondidas + 1 pendiente para probar)');

        // ── 16. Facturas ──
        // Factura emitida vinculada a comisión
        $pagoVal = $commissions->where('status', CommissionStatus::PAGO_VALIDACION)->first();
        if ($pagoVal) {
            Invoice::create([
                'customer_id' => $pagoVal->client_id,
                'commission_id' => $pagoVal->id,
                'franchise_id' => $pagoVal->franchise_id,
                'branch_id' => $pagoVal->branch_id,
                'user_id' => $admin->id,
                'tipo_comprobante' => InvoiceType::FACTURA_B->value,
                'punto_venta' => 5,
                'numero_comprobante' => 1,
                'fecha_emision' => $today->copy()->subDays(2)->format('Y-m-d'),
                'cae' => '71234567890123',
                'cae_vencimiento' => $today->copy()->addDays(10)->format('Ymd'),
                'importe_total' => $pagoVal->total,
                'importe_neto' => round($pagoVal->total / 1.21, 2),
                'importe_iva' => round($pagoVal->total - ($pagoVal->total / 1.21), 2),
                'iva_rate' => 21,
                'doc_tipo' => 99,
                'razon_social' => Customer::find($pagoVal->client_id)->name . ' ' . Customer::find($pagoVal->client_id)->last_name,
                'condicion_iva' => 'Consumidor Final',
                'concepto' => 2,
                'status' => 'emitida',
            ]);
        }

        // Factura A (IVA discriminado)
        Invoice::create([
            'customer_id' => $customers[0]->id,
            'franchise_id' => $franchiseA->id,
            'branch_id' => $branchCentral->id,
            'user_id' => $admin->id,
            'tipo_comprobante' => InvoiceType::FACTURA_A->value,
            'punto_venta' => 5,
            'numero_comprobante' => 2,
            'fecha_emision' => $today->copy()->subDays(5)->format('Y-m-d'),
            'cae' => '71234567890124',
            'cae_vencimiento' => $today->copy()->addDays(7)->format('Ymd'),
            'importe_total' => 12100,
            'importe_neto' => 10000,
            'importe_iva' => 2100,
            'iva_rate' => 21,
            'doc_tipo' => 80,
            'doc_numero' => '30111222339',
            'razon_social' => 'Empresa MercadoLibre',
            'domicilio_cliente' => 'CABA',
            'condicion_iva' => 'IVA Responsable Inscripto',
            'concepto' => 2,
            'status' => 'emitida',
        ]);

        // Factura vinculada a pago
        Invoice::create([
            'customer_id' => $customers[1]->id,
            'current_account_id' => $confirmedPayment2->id,
            'franchise_id' => $franchiseA->id,
            'branch_id' => $branchCentral->id,
            'user_id' => $admin->id,
            'tipo_comprobante' => InvoiceType::FACTURA_B->value,
            'punto_venta' => 5,
            'numero_comprobante' => 3,
            'fecha_emision' => $today->copy()->subDays(3)->format('Y-m-d'),
            'cae' => '71234567890125',
            'cae_vencimiento' => $today->copy()->addDays(8)->format('Ymd'),
            'importe_total' => 8500,
            'importe_neto' => round(8500 / 1.21, 2),
            'importe_iva' => round(8500 - (8500 / 1.21), 2),
            'iva_rate' => 21,
            'doc_tipo' => 99,
            'razon_social' => 'Farmacia San Jorge',
            'condicion_iva' => 'Consumidor Final',
            'concepto' => 2,
            'status' => 'emitida',
        ]);

        // Factura anulada
        Invoice::create([
            'customer_id' => $customers[3]->id,
            'franchise_id' => $franchiseA->id,
            'branch_id' => $branchCentral->id,
            'user_id' => $admin->id,
            'tipo_comprobante' => InvoiceType::FACTURA_B->value,
            'punto_venta' => 5,
            'numero_comprobante' => 4,
            'fecha_emision' => $today->copy()->subDays(15)->format('Y-m-d'),
            'cae' => '71234567890126',
            'cae_vencimiento' => $today->copy()->subDays(5)->format('Ymd'),
            'importe_total' => 3500,
            'importe_neto' => round(3500 / 1.21, 2),
            'importe_iva' => round(3500 - (3500 / 1.21), 2),
            'iva_rate' => 21,
            'doc_tipo' => 99,
            'razon_social' => 'Estudio Contable López',
            'condicion_iva' => 'Consumidor Final',
            'concepto' => 2,
            'status' => 'anulada',
            'observaciones' => 'Anulada por error en datos del cliente',
        ]);

        // Factura franquicia B
        Invoice::create([
            'customer_id' => $customers[5]->id,
            'current_account_id' => $confirmedPaymentB->id,
            'franchise_id' => $franchiseB->id,
            'branch_id' => $branchCordoba->id,
            'user_id' => $adminFranquiciaB->id,
            'tipo_comprobante' => InvoiceType::FACTURA_B->value,
            'punto_venta' => 5,
            'numero_comprobante' => 5,
            'fecha_emision' => $today->copy()->subDays(2)->format('Y-m-d'),
            'cae' => '71234567890127',
            'cae_vencimiento' => $today->copy()->addDays(10)->format('Ymd'),
            'importe_total' => 6000,
            'importe_neto' => round(6000 / 1.21, 2),
            'importe_iva' => round(6000 - (6000 / 1.21), 2),
            'iva_rate' => 21,
            'doc_tipo' => 99,
            'razon_social' => 'Distribuidora Córdoba Sur',
            'condicion_iva' => 'Consumidor Final',
            'concepto' => 2,
            'status' => 'emitida',
        ]);

        $this->command->info('  ✓ 5 facturas (FA, FB, vinculada comisión, vinculada pago, anulada, otra franquicia)');

        // ── Resumen ──
        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════');
        $this->command->info('  QA SEED COMPLETO');
        $this->command->info('═══════════════════════════════════════════');
        $this->command->info('');
        $this->command->info('  USUARIOS (password: "password"):');
        $this->command->info('  ┌──────────────────────┬─────────────────────┬──────────────────┐');
        $this->command->info('  │ Rol                  │ Email               │ Franquicia       │');
        $this->command->info('  ├──────────────────────┼─────────────────────┼──────────────────┤');
        $this->command->info('  │ administrador         │ admin@ryr.com.ar    │ Todas            │');
        $this->command->info('  │ admin_franquicia      │ laura@ryr.com.ar    │ Buenos Aires     │');
        $this->command->info('  │ admin_franquicia      │ carlos@ryr.com.ar   │ Córdoba          │');
        $this->command->info('  │ mostrador             │ mostrador@ryr.com.ar│ Buenos Aires     │');
        $this->command->info('  │ cadete                │ cadete@ryr.com.ar   │ Buenos Aires     │');
        $this->command->info('  │ cadete_externo        │ cadete.ext@ryr.com.ar│ Buenos Aires    │');
        $this->command->info('  │ cobrador              │ cobrador@ryr.com.ar │ Buenos Aires     │');
        $this->command->info('  │ cadete (CBA)          │ cadete.cba@ryr.com.ar│ Córdoba         │');
        $this->command->info('  │ cliente               │ cliente@ryr.com.ar  │ —                │');
        $this->command->info('  └──────────────────────┴─────────────────────┴──────────────────┘');
        $this->command->info('');
        $this->command->info('  DATOS PARA TESTING:');
        $this->command->info('  • 2 franquicias (BSAS 10%, CBA 8.5%)');
        $this->command->info('  • 3 sucursales');
        $this->command->info('  • 8 clientes (IVA: 2 always, 4 auto, 1 exempt, 1 sin móvil)');
        $this->command->info('  • 15 comisiones (7 estados distintos)');
        $this->command->info('  • 5 movimientos cuenta corriente');
        $this->command->info('  • 5 gastos + 5 ingresos');
        $this->command->info('  • 2+ matrix receivables (pendientes + pagado)');
        $this->command->info('  • 4 campañas WhatsApp (draft, completed, scheduled, otra franquicia)');
        $this->command->info('  • 3 encuestas feedback (2 respondidas + 1 pendiente)');
        $this->command->info('  • 5 facturas (FA, FB, anulada, vinculadas)');
        $this->command->info('');
        $this->command->info('  FEEDBACK PENDIENTE (para probar respuesta pública):');
        $this->command->info('  → /feedback/qa-test-token-feedback-12345');
        $this->command->info('');
    }
}
