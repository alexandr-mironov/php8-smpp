<?php

declare(strict_types=1);

namespace Smpp\Protocol;

/**
 * Class Command
 *
 * Command ids - SMPP v3.4 - 5.1.2.1 page 110-111
 *
 * response command id can be gets by $responseCommandId = $commandId | Command::GENERIC_NACK
 * response command id constants maybe unused for this reason
 *
 * @package Smpp\Protocol
 */
class Command
{
    public const BIND_RECEIVER         = 0x00000001;
    public const BIND_RECEIVER_RESP    = 0x80000001;

    public const BIND_TRANSMITTER      = 0x00000002;
    public const BIND_TRANSMITTER_RESP = 0x80000002;

    public const QUERY_SM              = 0x00000003;
    public const QUERY_SM_RESP         = 0x80000003;

    public const SUBMIT_SM             = 0x00000004;
    public const SUBMIT_SM_RESP        = 0x80000004;

    public const DELIVER_SM            = 0x00000005;
    public const DELIVER_SM_RESP       = 0x80000005;

    public const UNBIND                = 0x00000006;
    public const UNBIND_RESP           = 0x80000006;

    public const REPLACE_SM            = 0x00000007;
    public const REPLACE_SM_RESP       = 0x80000007;

    public const CANCEL_SM             = 0x00000008;
    public const CANCEL_SM_RESP        = 0x80000008;

    public const BIND_TRANSCEIVER      = 0x00000009;
    public const BIND_TRANSCEIVER_RESP = 0x80000009;

    public const OUTBIND               = 0x0000000B;

    public const ENQUIRE_LINK          = 0x00000015;
    public const ENQUIRE_LINK_RESP     = 0x80000015;

    public const GENERIC_NACK          = 0x80000000;

}