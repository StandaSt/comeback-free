<?php
declare(strict_types=1);
/* Účel: Předává nové evidenci skutečného autora z přihlášení IS nebo ověřené identity Co-Linku, nikdy z HTTP parametrů. */

/** Objekt vytváří pouze serverová autentizace; samotné načtení knihovny neotevírá session ani Co-Link. */
final class CbCinnostIdentita
{
    private function __construct(public readonly int $idUser, public readonly ?int $idLogin, public readonly ?int $idUserPrava) {}

    /** Převzatý profil práv nemění autora; jeho ID uchováme zvlášť. */
    public static function zIs(): self
    {
        $user = (int)($_SESSION['cb_user']['id_user'] ?? 0);
        if (empty($_SESSION['login_ok']) || $user < 1) throw new RuntimeException('Činnost vyžaduje přihlášeného uživatele.');
        $profile = function_exists('cb_prebrani_prav_profil') ? cb_prebrani_prav_profil($user) : null;
        return new self($user, (int)($_SESSION['cb_id_login'] ?? 0) > 0 ? (int)$_SESSION['cb_id_login'] : null,
            $profile === null ? null : (int)$profile['id_user_zdroj']);
    }

    /** Samostatný Co-Link používá vlastní ověřenou cookie, nikoli případnou session jiného účtu IS. */
    public static function zCoLinku(): self
    {
        if (!function_exists('co_identita')) throw new RuntimeException('Není načtená autentizace Co-Linku.');
        $identity = co_identita();
        $user = (int)($identity['id_user'] ?? 0);
        if ($user < 1) throw new RuntimeException('Co-Link nemá ověřeného uživatele.');
        return new self($user, null, null);
    }
}
