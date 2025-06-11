<?php

declare(strict_types=1);

namespace Smpp;

/**
 * Numerous constants for SMPP v3.4
 * Based on specification at: http://www.smsforum.net/SMPP_v3_4_Issue1_2.zip
 */
class Smpp
{
    // SMPP v3.4 - 5.2.5 page 117
    const TON_UNKNOWN          = 0x00;
    const TON_INTERNATIONAL    = 0x01;
    const TON_NATIONAL         = 0x02;
    const TON_NETWORKSPECIFIC  = 0x03;
    const TON_SUBSCRIBERNUMBER = 0x04;
    const TON_ALPHANUMERIC     = 0x05;
    const TON_ABBREVIATED      = 0x06;

    // SMPP v3.4 - 5.2.6 page 118
    const NPI_UNKNOWN   = 0x00;
    const NPI_E164      = 0x01;
    const NPI_DATA      = 0x03;
    const NPI_TELEX     = 0x04;
    const NPI_E212      = 0x06;
    const NPI_NATIONAL  = 0x08;
    const NPI_PRIVATE   = 0x09;
    const NPI_ERMES     = 0x0a;
    const NPI_INTERNET  = 0x0e;
    const NPI_WAPCLIENT = 0x12;

    // ESM bits 1-0 - SMPP v3.4 - 5.2.12 page 121-122
    const ESM_SUBMIT_MODE_DATAGRAM        = 0x01;
    const ESM_SUBMIT_MODE_FORWARD         = 0x02;
    const ESM_SUBMIT_MODE_STOREANDFORWARD = 0x03;
    // ESM bits 5-2
    const ESM_SUBMIT_BINARY          = 0x04;
    const ESM_SUBMIT_TYPE_ESME_D_ACK = 0x08;
    const ESM_SUBMIT_TYPE_ESME_U_ACK = 0x10;
    const ESM_DELIVER_SMSC_RECEIPT   = 0x04;
    const ESM_DELIVER_SME_ACK        = 0x08;
    const ESM_DELIVER_U_ACK          = 0x10;
    const ESM_DELIVER_CONV_ABORT     = 0x18;
    const ESM_DELIVER_IDN            = 0x20; // Intermediate delivery notification
    // ESM bits 7-6
    const ESM_UHDI      = 0x40;
    const ESM_REPLYPATH = 0x80;

    // SMPP v3.4 - 5.2.17 page 124
    const REG_DELIVERY_NO          = 0x00;
    const REG_DELIVERY_SMSC_BOTH   = 0x01; // both success and failure
    const REG_DELIVERY_SMSC_FAILED = 0x02;
    const REG_DELIVERY_SME_D_ACK   = 0x04;
    const REG_DELIVERY_SME_U_ACK   = 0x08;
    const REG_DELIVERY_SME_BOTH    = 0x0c;
    const REG_DELIVERY_IDN         = 0x10; // Intermediate notification

    // SMPP v3.4 - 5.2.18 page 125
    const REPLACE_NO  = 0x00;
    const REPLACE_YES = 0x01;

    // SMPP v3.4 - 5.2.19 page 126
    const DATA_CODING_DEFAULT      = 0;
    const DATA_CODING_IA5          = 1; // IA5 (CCITT T.50)/ASCII (ANSI X3.4)
    const DATA_CODING_BINARY_ALIAS = 2;
    const DATA_CODING_ISO8859_1    = 3; // Latin 1
    const DATA_CODING_BINARY       = 4;
    const DATA_CODING_JIS          = 5;
    const DATA_CODING_ISO8859_5    = 6; // Cyrillic
    const DATA_CODING_ISO8859_8    = 7; // Latin/Hebrew
    const DATA_CODING_UCS2         = 8; // UCS-2BE (Big Endian)
    const DATA_CODING_PICTOGRAM    = 9;
    const DATA_CODING_ISO2022_JP   = 10; // Music codes
    const DATA_CODING_KANJI        = 13; // Extended Kanji JIS
    const DATA_CODING_KSC5601      = 14;

    // SMPP v3.4 - 5.2.25 page 129
    const DEST_FLAG_SME      = 1;
    const DEST_FLAG_DISTLIST = 2;

    // SMPP v3.4 - 5.2.28 page 130
    const STATE_ENROUTE       = 1;
    const STATE_DELIVERED     = 2;
    const STATE_EXPIRED       = 3;
    const STATE_DELETED       = 4;
    const STATE_UNDELIVERABLE = 5;
    const STATE_ACCEPTED      = 6;
    const STATE_UNKNOWN       = 7;
    const STATE_REJECTED      = 8;

    // Message concatenating
    // CSMS - Concatenated Short Message Service
    /**
     * @var integer Use sar_msg_ref_num and sar_total_segments with 16 bit tags
     */
    public const CSMS_16BIT_TAGS = 0;

    /**
     * @var integer Use message payload for CSMS (Concatenated Short Message Service)
     */
    public const CSMS_PAYLOAD = 1;

    /**
     * @var integer Embed a UDH (User Data Header) in the message with 8-bit reference.
     */
    public const CSMS_8BIT_UDH = 2;
}