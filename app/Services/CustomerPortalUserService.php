<?php

namespace App\Services;

use App\Shared\Enums\UserRole;
use App\Shared\Models\Customer;
use App\Shared\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Mantiene coherentes el email del cliente y su usuario de portal (rol cliente).
 *
 * RC-518: el vínculo cliente ↔ usuario de portal no es uniforme en la base. En la
 * copia de producción del 23/09, 2.924 clientes vivos tienen customers.user_id
 * apuntando al administrador que los migró, así que para ellos el único vínculo es el
 * email. Y customers.email / users.email son únicos a nivel base incluso para filas
 * dadas de baja: el cliente de prueba "test nico" (borrado el 25/02/2026) seguía
 * reteniendo nicolasrg27@gmail.com y nadie podía ponérselo al cliente real.
 */
class CustomerPortalUserService
{
    /**
     * Usuario de portal del cliente, o null si todavía no tiene.
     *
     * Primero por email (el criterio histórico del login por código), después por
     * customers.user_id cuando apunta a un usuario cliente. Un usuario que ya es el
     * acceso de otro cliente vivo no cuenta: devolverlo abriría el portal equivocado.
     */
    public function portalUserOf(Customer $customer, ?string $email = null): ?User
    {
        $email ??= $customer->email;

        if ($email) {
            $porEmail = User::where('email', $email)->where('role', UserRole::CLIENTE->value)->first();

            if ($porEmail && ! $this->otherCustomerLinkedTo($porEmail, $customer)) {
                return $porEmail;
            }
        }

        if ($customer->user_id) {
            $vinculado = User::find($customer->user_id);

            if ($vinculado && $vinculado->role === UserRole::CLIENTE) {
                return $vinculado;
            }
        }

        return null;
    }

    /**
     * Cliente vivo detrás de un usuario de portal que no se encontró por email.
     */
    public function customerOf(User $user): ?Customer
    {
        return Customer::where('user_id', $user->id)->first();
    }

    /**
     * Motivo legible por el que $email no puede pasar a ser el email del cliente, o
     * null si se puede. Los clientes y usuarios dados de baja no bloquean: su email se
     * libera al guardar (releaseFromDeleted).
     */
    public function emailConflict(Customer $customer, string $email): ?string
    {
        $otroCliente = Customer::where('email', $email)->where('id', '!=', $customer->id)->first();

        if ($otroCliente) {
            return "El email {$email} ya lo usa el cliente {$this->describe($otroCliente)}.";
        }

        $usuario = User::where('email', $email)->where('role', UserRole::CLIENTE->value)->first();
        $duenio = $usuario ? $this->otherCustomerLinkedTo($usuario, $customer) : null;

        if ($duenio) {
            return "El email {$email} ya es el acceso al portal del cliente {$this->describe($duenio)}.";
        }

        return null;
    }

    /**
     * Libera $email de clientes y usuarios dados de baja, que lo siguen reservando en
     * los índices únicos. El email original queda en el log.
     */
    public function releaseFromDeleted(string $email, Customer $except): void
    {
        Customer::onlyTrashed()
            ->where('email', $email)
            ->where('id', '!=', $except->id)
            ->get()
            ->each(function (Customer $borrado) use ($email, $except) {
                $borrado->forceFill(['email' => "cliente-{$borrado->id}-eliminado@ryrcomisiones.com"])->save();

                Log::info('Email liberado de un cliente dado de baja', [
                    'email' => $email,
                    'deleted_customer_id' => $borrado->id,
                    'customer_id' => $except->id,
                ]);
            });

        User::onlyTrashed()
            ->where('email', $email)
            ->get()
            ->each(function (User $borrado) use ($email, $except) {
                $borrado->forceFill(['email' => "usuario-{$borrado->id}-eliminado@ryrcomisiones.com"])->save();

                Log::info('Email liberado de un usuario dado de baja', [
                    'email' => $email,
                    'deleted_user_id' => $borrado->id,
                    'customer_id' => $except->id,
                ]);
            });
    }

    /**
     * Después de cambiar el email del cliente, deja su usuario de portal con ese mismo
     * email y vinculado por customers.user_id.
     *
     * Si el nuevo email ya tiene un usuario cliente huérfano (su cliente fue dado de
     * baja), se lo adopta en vez de dejar dos usuarios: es la misma casilla, y el login
     * por código busca al usuario por email. Si el email es de un empleado no se toca
     * nada: el cliente conserva el acceso que ya tenía.
     */
    public function syncAfterEmailChange(Customer $customer, string $oldEmail): void
    {
        $portal = $this->portalUserOf($customer, $oldEmail);
        $titular = User::where('email', $customer->email)->first();

        if (! $titular) {
            if ($portal) {
                $portal->email = $customer->email;
                $portal->save();
                $this->link($customer, $portal);
            }

            return;
        }

        if ($portal && $titular->is($portal)) {
            $this->link($customer, $portal);

            return;
        }

        if ($titular->role === UserRole::CLIENTE && ! $this->otherCustomerLinkedTo($titular, $customer)) {
            $titular->name = $this->portalName($customer);
            $titular->save();
            $this->link($customer, $titular);

            Log::info('Usuario de portal huérfano adoptado al cambiar el email del cliente', [
                'customer_id' => $customer->id,
                'user_id' => $titular->id,
                'previous_portal_user_id' => $portal?->id,
            ]);
        }
    }

    public function link(Customer $customer, User $user): void
    {
        if ((int) $customer->user_id !== (int) $user->id) {
            $customer->forceFill(['user_id' => $user->id])->save();
        }
    }

    public function portalName(Customer $customer): string
    {
        return trim(($customer->name ?? '') . ' ' . ($customer->last_name ?? '')) ?: 'Cliente';
    }

    private function otherCustomerLinkedTo(User $user, Customer $customer): ?Customer
    {
        return Customer::where('user_id', $user->id)->where('id', '!=', $customer->id)->first();
    }

    private function describe(Customer $customer): string
    {
        $nombre = trim(($customer->name ?? '') . ' ' . ($customer->last_name ?? ''));
        $documento = match (true) {
            $customer->isCompany() && (bool) $customer->cuit => "CUIT {$customer->cuit}",
            (bool) $customer->dni => "DNI {$customer->dni}",
            (bool) $customer->cuit => "CUIT {$customer->cuit}",
            default => null,
        };

        return $nombre . ($documento ? " ({$documento})" : " (#{$customer->id})");
    }
}
