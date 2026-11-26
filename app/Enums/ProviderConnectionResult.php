<?php

namespace App\Enums;

/** Hasil percobaan menghubungkan provider ke akun yang sedang login. */
enum ProviderConnectionResult: string
{
    case Connected = 'connected';
    case AlreadyConnected = 'already_connected';
    case OwnedByAnotherUser = 'owned_by_another_user';
    case InvalidIdentity = 'invalid_identity';
}
