<?php
declare(strict_types=1);

use Nette\Neon\Neon;

require "vendor/autoload.php";

// Config
try {
    $config = Neon::decode(file_exists('config.neon') ? file_get_contents('config.neon') : file_get_contents('config.neon.dist'))['config']['accounts'];
} catch (Exception $e) {
    die("Syntax error in config file. Please check: " . $e->getMessage());
}

// DATEV file header
$datev = array();
$datev[0] = str_getcsv('Umsatz (ohne Soll/Haben-Kz);Soll/Haben-Kennzeichen;WKZ Umsatz;Kurs;Basis-Umsatz;WKZ Basis-Umsatz;Konto;Gegenkonto (ohne BU-Schlüssel);BU-Schlüssel;Belegdatum;Belegfeld 1;Belegfeld 2;Skonto;Buchungstext;Postensperre;Diverse Adressnummer;Geschäftspartnerbank;Sachverhalt;Zinssperre;Beleglink;Beleginfo - Art 1;Beleginfo - Inhalt 1;Beleginfo - Art 2;Beleginfo - Inhalt 2;Beleginfo - Art 3;Beleginfo - Inhalt 3;Beleginfo - Art 4;Beleginfo - Inhalt 4;Beleginfo - Art 5;Beleginfo - Inhalt 5;Beleginfo - Art 6;Beleginfo - Inhalt 6;Beleginfo - Art 7;Beleginfo - Inhalt 7;Beleginfo - Art 8;Beleginfo - Inhalt 8;KOST1 - Kostenstelle;KOST2 - Kostenstelle;Kost-Menge;EU-Land u. UStID;EU-Steuersatz;Abw. Versteuerungsart;Sachverhalt L+L;Funktionsergänzung L+L;BU 49 Hauptfunktionstyp;BU 49 Hauptfunktionsnummer;BU 49 Funktionsergänzung;Zusatzinformation - Art 1;Zusatzinformation- Inhalt 1;Zusatzinformation - Art 2;Zusatzinformation- Inhalt 2;Zusatzinformation - Art 3;Zusatzinformation- Inhalt 3;Zusatzinformation - Art 4;Zusatzinformation- Inhalt 4;Zusatzinformation - Art 5;Zusatzinformation- Inhalt 5;Zusatzinformation - Art 6;Zusatzinformation- Inhalt 6;Zusatzinformation - Art 7;Zusatzinformation- Inhalt 7;Zusatzinformation - Art 8;Zusatzinformation- Inhalt 8;Zusatzinformation - Art 9;Zusatzinformation- Inhalt 9;Zusatzinformation - Art 10;Zusatzinformation- Inhalt 10;Zusatzinformation - Art 11;Zusatzinformation- Inhalt 11;Zusatzinformation - Art 12;Zusatzinformation- Inhalt 12;Zusatzinformation - Art 13;Zusatzinformation- Inhalt 13;Zusatzinformation - Art 14;Zusatzinformation- Inhalt 14;Zusatzinformation - Art 15;Zusatzinformation- Inhalt 15;Zusatzinformation - Art 16;Zusatzinformation- Inhalt 16;Zusatzinformation - Art 17;Zusatzinformation- Inhalt 17;Zusatzinformation - Art 18;Zusatzinformation- Inhalt 18;Zusatzinformation - Art 19;Zusatzinformation- Inhalt 19;Zusatzinformation - Art 20;Zusatzinformation- Inhalt 20;Stück;Gewicht;Zahlweise;Forderungsart;Veranlagungsjahr;Zugeordnete Fälligkeit;Skontotyp;Auftragsnummer;Buchungstyp (Anzahlungen);USt-Schlüssel (Anzahlungen);EU-Land (Anzahlungen);Sachverhalt L+L (Anzahlungen);EU-Steuersatz (Anzahlungen);Erlöskonto (Anzahlungen);Herkunft-Kz;Buchungs GUID;KOST-Datum;SEPA-Mandatsreferenz;Skontosperre;Gesellschaftername;Beteiligtennummer;Identifikationsnummer;Zeichnernummer;Postensperre bis;Bezeichnung SoBil-Sachverhalt;Kennzeichen SoBil-Buchung;Festschreibung;Leistungsdatum;Datum Zuord. Steuerperiode;Fälligkeit;Generalumkehr (GU);Steuersatz;Land;Abrechnungsreferenz;BVV-Position',
    ';'
);

// parse Mollie report
$mollieFp = fopen('mollie-balance.csv', 'r') or die('Mollie Report could not be opened. Expected file mollie-balance.csv');
$lines = 1;
$mollie = [];
while (($line = fgetcsv($mollieFp, 2048, ',')) !== false) {
    $mollie[] = $line;
    $lines++;
}
fclose($mollieFp);
echo "Read mollie-balance.csv, $lines lines processed.\n";

// remove header line
array_shift($mollie);

// map it
$lineCounter = 1;
foreach ($mollie as $mollieLine) {
    // init line
    $datevLine = array();
    $datevLine = array_fill(0, 122, '');
    // date format DMM
    $datevLine[9] = date('jm', strtotime($mollieLine[0]));
    // determine transaction type and accounts
    switch ($mollieLine[1]) {
        case 'payment':
        case 'capture':
            $datevLine[6] = $config['clearing1'];
            $datevLine[7] = $config['debitor'];
            // if the amount is negative, it's a failed payment, then it should be H, otherwise S
            $datevLine[2] = ($mollieLine[7] < 0) ? 'H' : 'S';
            // Belegfeld 1: transaction ID
            $datevLine[10] = $mollieLine[3];
            // Buchungstext: transaction ID, order ID, consumer name
            $datevLine[13] = $mollieLine[3] . ' ' . $mollieLine[6] . ' ' . $mollieLine[4];
            break;
        case 'refund':
        case 'chargeback':
            $datevLine[6] = $config['clearing1'];
            $datevLine[7] = $config['debitor'];
            $datevLine[2] = 'H';
            // Belegfeld 1: transaction ID
            $datevLine[10] = $mollieLine[3];
            // Buchungstext: transaction ID, order ID, consumer name
            $datevLine[13] = $mollieLine[3] . ' ' . $mollieLine[6] . ' ' . $mollieLine[4];
            break;
        case 'transfer':
            $datevLine[6] = $config['clearing1'];
            $datevLine[7] = $config['clearing2'];
            $datevLine[2] = 'H';
            // Belegfeld 1: Transfer ID
            $datevLine[10] = $mollieLine[3];
            // Buchungstext: Transfer ID
            $datevLine[13] = $mollieLine[3];
            break;
        case 'invoice':
            // actual fees
            if ($mollieLine[7] < 0) {
                $datevLine[6] = $config['paymentfees'];
                $datevLine[7] = $config['clearing1'];
                $datevLine[2] = 'H';
                // Belegfeld 1: Invoice ID
                $datevLine[10] = str_replace('Withheld transaction fees ', '', $mollieLine[6]);
                // Buchungstext: withheld transaction fees w/ID
                $datevLine[13] = $mollieLine[6];
            } else { // else is invoice compensation
                $datevLine[6] = $config['paymentfees'];
                $datevLine[7] = $config['clearing1'];
                $datevLine[2] = 'S';
                // Belegfeld 1: Invoice ID
                $datevLine[10] = $mollieLine[3];
                // Buchungstext: Invoice ID with text
                $datevLine[13] = $mollieLine[6];
            }
            // Fixed value for Buchungsschlüssel
            $datevLine[8] = 506;
            break;
        default:
            echo "Error: Unsupported transaction type " . $mollieLine[1] . " in line " . $lineCounter . "\n";
            die();
    }
    // amount as absolute
    $datevLine[0] = number_format(abs((float)$mollieLine[7]), 2, ',', '');
    // Festschreibung fixed value
    $datevLine[113] = 0;
    $datev[] = $datevLine;
    $lineCounter++;
}

$datevFp = fopen('mollie-datev.csv', 'x') or die("Could not create mollie-datev.csv. Maybe the file already exists?");
$lineCounter = 1;
foreach ($datev as $datevOut) {
    fputcsv($datevFp, $datevOut, ";");
    $lineCounter++;
}
fclose($datevFp);

echo "DATEV report written to mollie-datev.csv with " . $lineCounter . " lines.\n";