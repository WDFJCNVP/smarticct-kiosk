<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ThermalReceiptService
{
    const ESC = "\x1b";
    const GS  = "\x1d";

    /**
     * Print Operator Queueing Slip from Kiosk
     */
    public static function printKioskQueueSlip(array $data): bool
    {
        $receipt = self::ESC . "@";                  // Reset
        $receipt .= self::ESC . "M" . "\x00";        // Hardware Font A (12x24)
        
        // Header
        $receipt .= self::ESC . "a" . "\x01";        // Center
        $receipt .= self::ESC . "E" . "\x01";        // Bold ON
        $receipt .= self::GS . "!" . "\x10";         // Double height
        $receipt .= "SMART ICCT\n";
        $receipt .= self::GS . "!" . "\x00";         // Normal size
        $receipt .= "KIOSK QUEUE SLIP\n";
        $receipt .= self::ESC . "E" . "\x00";        // Bold OFF
        $receipt .= "--------------------------------\n";

        // Body
        $receipt .= self::ESC . "a" . "\x00";        // Left
        $receipt .= self::makeRow("Ref:", substr($data['reference_no'], 0, 24));
        $receipt .= self::makeRow("Date:", $data['date']);
        $receipt .= self::makeRow("Operator:", substr($data['operator_name'], 0, 20));
        $receipt .= self::makeRow("Driver:", substr($data['driver_name'], 0, 22));

        $receipt .= self::ESC . "E" . "\x01";        // Bold ON
        $receipt .= self::makeRow("Plate:", $data['plate_number']);
        $receipt .= self::ESC . "E" . "\x00";        // Bold OFF

        $receipt .= self::makeRow("Type:", $data['vehicle_type']);
        $receipt .= self::makeRow("Route:", "Iriga -> " . substr($data['destination'], 0, 18));
        $receipt .= "--------------------------------\n";

        // Deduction & Balances
        $receipt .= self::ESC . "E" . "\x01";
        $receipt .= self::makeRow("Queue Fee:", "PHP " . number_format($data['fee'], 2));
        $receipt .= self::ESC . "E" . "\x00";
        $receipt .= self::makeRow("New Balance:", "PHP " . number_format($data['balance_after'], 2));

        // Footer
        $receipt .= "--------------------------------\n";
        $receipt .= self::ESC . "a" . "\x01";        // Center
        $receipt .= "PLEASE PROCEED TO STAGING\n";
        $receipt .= "KEEP THIS TICKET\n";
        $receipt .= "\n\n\n\n";                      // Feed past tear bar

        return self::sendToPrinter($receipt);
    }

    /**
     * Print Commuter Passenger Fare / Boarding Slip
     */
    public static function printCommuterFareSlip(array $data): bool
    {
        $receipt = self::ESC . "@";
        $receipt .= self::ESC . "M" . "\x00";
        
        // Header
        $receipt .= self::ESC . "a" . "\x01";
        $receipt .= self::ESC . "E" . "\x01";
        $receipt .= self::GS . "!" . "\x10";
        $receipt .= "SMART ICCT\n";
        $receipt .= self::GS . "!" . "\x00";
        $receipt .= "PASSENGER BOARDING PASS\n";
        $receipt .= self::ESC . "E" . "\x00";
        $receipt .= "--------------------------------\n";

        // Ride Information
        $receipt .= self::ESC . "a" . "\x00";
        $receipt .= self::makeRow("Ticket No:", substr($data['reference_no'], 0, 20));
        $receipt .= self::makeRow("Date/Time:", $data['date']);
        $receipt .= self::makeRow("Passenger:", substr($data['passenger_name'], 0, 20));
        $receipt .= self::makeRow("Type:", $data['passenger_type'] ?? 'Regular');
        $receipt .= "--------------------------------\n";

        $receipt .= self::ESC . "E" . "\x01";
        $receipt .= self::makeRow("Destination:", substr($data['destination'], 0, 18));
        $receipt .= self::makeRow("Vehicle:", $data['vehicle_type']);
        $receipt .= self::makeRow("Plate No:", $data['plate_number']);
        $receipt .= self::ESC . "E" . "\x00";
        $receipt .= "--------------------------------\n";

        // Payment
        $receipt .= self::ESC . "E" . "\x01";
        $receipt .= self::makeRow("Fare Paid:", "PHP " . number_format($data['fare'], 2));
        $receipt .= self::ESC . "E" . "\x00";
        $receipt .= self::makeRow("Card Balance:", "PHP " . number_format($data['balance_after'], 2));

        // Footer
        $receipt .= "--------------------------------\n";
        $receipt .= self::ESC . "a" . "\x01";
        $receipt .= "SHOW THIS UPON BOARDING\n";
        $receipt .= "HAVE A SAFE TRIP!\n";
        $receipt .= "\n\n\n\n";

        return self::sendToPrinter($receipt);
    }

    private static function sendToPrinter(string $bytes): bool
    {
        $printerPath = "\\\\127.0.0.1\\POS58";

        try {
            $handle = fopen($printerPath, "wb");
            if ($handle) {
                fwrite($handle, $bytes);
                fclose($handle);
                return true;
            }
        } catch (\Exception $e) {
            Log::error("Thermal Print Error: " . $e->getMessage());
        }

        return false;
    }

    private static function makeRow(string $left, string $right, int $width = 32): string
    {
        $spaces = max(1, $width - strlen($left) - strlen($right));
        return $left . str_repeat(' ', $spaces) . $right . "\n";
    }

    public static function printCommuterFareSlips(array $data): bool
    {
        $receipt = self::ESC . "@";                  // Initialize
        $receipt .= self::ESC . "M" . "\x00";        // Hardware Font A (12x24)
        
        // Header
        $receipt .= self::ESC . "a" . "\x01";        // Center
        $receipt .= self::ESC . "E" . "\x01";        // Bold ON
        $receipt .= self::GS . "!" . "\x10";         // Double height text
        $receipt .= "SMART ICCT\n";
        $receipt .= self::GS . "!" . "\x00";         // Normal size
        $receipt .= "PASSENGER BOARDING PASS\n";
        $receipt .= self::ESC . "E" . "\x00";        // Bold OFF
        $receipt .= "--------------------------------\n"; // 32 chars

        // Details
        $receipt .= self::ESC . "a" . "\x00";        // Left
        $receipt .= self::makeRow("Ticket:", substr($data['reference_no'], 0, 24));
        $receipt .= self::makeRow("Date:", $data['date']);
        $receipt .= self::makeRow("Passenger:", substr($data['passenger_name'], 0, 21));
        $receipt .= self::makeRow("Type:", ucfirst($data['passenger_type'] ?? 'Regular'));
        $receipt .= "--------------------------------\n";

        // Ride details (Bold)
        $receipt .= self::ESC . "E" . "\x01";
        $receipt .= self::makeRow("Destination:", substr($data['destination'], 0, 19));
        $receipt .= self::makeRow("Vehicle:", $data['vehicle_type']);
        $receipt .= self::makeRow("Plate:", $data['plate_number']);
        $receipt .= self::ESC . "E" . "\x00";
        $receipt .= "--------------------------------\n";

        // Payment
        $receipt .= self::ESC . "E" . "\x01";
        $receipt .= self::makeRow("Fare Paid:", "PHP " . number_format((float)$data['fare'], 2));
        $receipt .= self::ESC . "E" . "\x00";
        $receipt .= self::makeRow("Remaining Bal:", "PHP " . number_format((float)$data['balance_after'], 2));

        // Footer
        $receipt .= "--------------------------------\n";
        $receipt .= self::ESC . "a" . "\x01";        // Center
        $receipt .= "SHOW THIS UPON BOARDING\n";
        $receipt .= "HAVE A SAFE TRIP!\n";
        $receipt .= "\n\n\n";
        $receipt .= "\x0c";                          // End of job (stops printer loops)

        return self::sendToPrinter($receipt);
    }
}