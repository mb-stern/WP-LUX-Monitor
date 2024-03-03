<?php

class WPLUX extends IPSModule
{
    private $updateTimer;

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString('IPAddress', '0.0.0.0');
        $this->RegisterPropertyInteger('Port', 8889);
        $this->RegisterPropertyString('IDListe', '[]');
        $this->RegisterPropertyInteger('UpdateInterval', 0);
        
        $this->RegisterPropertyBoolean('HeizungVisible', false);
        $this->RegisterPropertyBoolean('KuehlungVisible', false);
        $this->RegisterPropertyBoolean('WarmwasserVisible', false);
        $this->RegisterPropertyBoolean('TempsetVisible', false);
        $this->RegisterPropertyBoolean('WWsetVisible', false);
        $this->RegisterPropertyFloat('kwin', 0);
        $this->RegisterPropertyFloat('kwhin', 0);
        $this->RegisterPropertyBoolean('TimerWeekVisible', false);
        $this->RegisterPropertyBoolean('TimerWeekendVisible', false);
        $this->RegisterPropertyBoolean('TimerDayVisible', false);

        $this->RegisterAttributeFloat("start_value_out", 0);
        $this->RegisterAttributeFloat("start_kwh_in", 0);

        // Timer für Aktualisierung registrieren
        $this->RegisterTimer('UpdateTimer', 0, 'WPLUX_Update(' . $this->InstanceID . ');');  
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        //Variableprofile erstellen wenn nicht vorhanden
        require_once __DIR__ . '/variable_profile.php';

        // Timer für Aktualisierung aktualisieren
        $this->SetTimerInterval('UpdateTimer', $this->ReadPropertyInteger('UpdateInterval') * 1000);
    
        // Hole die IP-Adresse und andere Konfigurationseinstellungen
        $ipAddress = $this->ReadPropertyString('IPAddress');
        $port = $this->ReadPropertyInteger('Port');

        // Überprüfe, ob die IP-Adresse nicht die Muster-IP ist
        if ($ipAddress == '0.0.0.0') 
        {
            $this->SendDebug("Konfiguration", "IP-Adresse ist nicht konfiguriert", 0);   
            $this->LogMessage("IP-Adresse ist nicht konfiguriert", KL_ERROR);
        } 
        else 
        {
            // Bei Änderungen am Konfigurationsformular oder bei der Initialisierung auslösen
            $this->Update();
        }

        // Überprüfen, ob die Checkboxen im Konfigurationsformuler zum erstellen der Variablen aktiviert sind
        $heizungVisible = $this->ReadPropertyBoolean('HeizungVisible');
        $kuehlungVisible = $this->ReadPropertyBoolean('KuehlungVisible');
        $warmwasserVisible = $this->ReadPropertyBoolean('WarmwasserVisible');
        $tempsetVisible = $this->ReadPropertyBoolean('TempsetVisible');
        $wwsetVisible = $this->ReadPropertyBoolean('WWsetVisible');
        $copVisible = $this->ReadPropertyFloat('kwin');
        $jazVisible = $this->ReadPropertyFloat('kwhin');
        $timerWeekVisible = $this->ReadPropertyBoolean('TimerWeekVisible');
        $timerWeekendVisible = $this->ReadPropertyBoolean('TimerWeekendVisible');
        $timerDayVisible = $this->ReadPropertyBoolean('TimerDayVisible');

        // Steuervariablen erstellen und senden an die Funktion RequestAction
        if ($heizungVisible) 
        {
            $this->RegisterVariableInteger('HeizungVariable', 'Modus Heizung', 'WPLUX.Wwhe', 0);
            $this->getParameter('Heizung');
            $Value = $this->GetValue('HeizungVariable');
            $this->EnableAction('HeizungVariable');
        } 
        else 
        {
            $this->UnregisterVariable('HeizungVariable');
        }

        if ($warmwasserVisible) 
        {
            $this->RegisterVariableInteger('WarmwasserVariable', 'Modus Warmwasser', 'WPLUX.Wwhe', 1);
            $this->getParameter('Warmwasser');
            $Value = $this->GetValue('WarmwasserVariable');
            $this->EnableAction('WarmwasserVariable');
        } 
        else 
        {
            $this->UnregisterVariable('WarmwasserVariable');
        }

        if ($kuehlungVisible) 
        {
            $this->RegisterVariableInteger('KuehlungVariable', 'Modus Kühlung', 'WPLUX.Kue', 2);
            $this->getParameter('Kuehlung');
            $Value = $this->GetValue('KuehlungVariable');   
            $this->EnableAction('KuehlungVariable');
        } 
        else 
        {
            $this->UnregisterVariable('KuehlungVariable');
        }

        if ($tempsetVisible) 
        {
            $this->RegisterVariableFloat('TempsetVariable', 'Temperaturkorrektur', 'WPLUX.Tset', 3);
            $this->getParameter('Tempset'); 
            $Value = $this->GetValue('TempsetVariable'); 
            $this->EnableAction('TempsetVariable');
        } 
        else 
        {
            $this->UnregisterVariable('TempsetVariable');
        }

        if ($wwsetVisible) 
        {
            $this->RegisterVariableFloat('WWsetVariable', 'Warmwasser Soll', 'WPLUX.Wset', 4);
            $this->getParameter('Wset'); 
            $Value = $this->GetValue('WWsetVariable'); 
            $this->EnableAction('WWsetVariable');
        } 
        else 
        {
            $this->UnregisterVariable('WWsetVariable');
        }

        if ($copVisible !== 0 && IPS_VariableExists($copVisible)) 
        {
            $this->RegisterVariableFloat('copfaktor', 'COP-Faktor', '', 5);
        } 
        else 
        {
            $this->UnregisterVariable('copfaktor');
        }
        
        if ($jazVisible !== 0 && IPS_VariableExists($jazVisible)) 
        {
            $this->RegisterVariableFloat('jazfaktor', 'JAZ-Faktor', '', 6);
        } 
        else 
        {
            $this->UnregisterVariable('jazfaktor');
        }

        if ($timerWeekVisible) 
        {
            $ids = [
                '223' => 'Woche von Set 1', '224' => 'Woche bis Set 1',
                '225' => 'Woche von Set 2', '226' => 'Woche bis Set 2',
                '227' => 'Woche von Set 3', '228' => 'Woche bis Set 3'
            ];
            
            $position = -60; //ab dieser Position im Objektbaum

            foreach ($ids as $id => $name) 
            {
                $this->RegisterVariableInteger($id, $name, '~UnixTimestampTime', $position++);
                $this->getParameter($id);
                $this->GetValue($id);
                $this->EnableAction($id);
            }
        } 
        else 
        {
            $ids = 
            [
                '223', '224', '225', '226', '227', '228'
            ];
            
            foreach ($ids as $id) 
            {
                $this->UnregisterVariable($id);
            }
        }

        if ($timerWeekendVisible) 
        {
            $ids = [
                '229' => 'Mo-Fr von Set 1', '230' => 'Mo-Fr bis Set 1', '231' => 'Mo-Fr von Set 2', '232' => 'Mo-Fr bis Set 2', '233' => 'Mo-Fr von Set 3', '234' => 'Mo-Fr bis Set 3',
                '235' => 'Sa+So von Set 1', '236' => 'Sa+So bis Set 1', '237' => 'Sa+So von Set 2', '238' => 'Sa+So bis Set 2', '239' => 'Sa+So von Set 3', '240' => 'Sa+So bis Set 3'
            ];

            $position = -54; //ab dieser Position im Objektbaum
            
            foreach ($ids as $id => $name) 
            {
                $this->RegisterVariableInteger($id, $name, '~UnixTimestampTime', $position++);
                $this->getParameter($id);
                $this->GetValue($id);
                $this->EnableAction($id);
            }
        } 
        else 
        {
            $ids = 
            [
                '229', '230', '231', '232', '233', '234', '235', '236', '237', '238', '239', '240'
            ];
            
            foreach ($ids as $id) 
            {
                $this->UnregisterVariable($id);
            }
        }

        if ($timerDayVisible) 
        {
            $ids = [
                '241' => 'Sonntag von Set 1', '242' => 'Sonntag bis Set 1', '243' => 'Sonntag von Set 2', '244' => 'Sonntag bis Set 2', '245' => 'Sonntag von Set 3', '246' => 'Sonntag bis Set 3',
                '247' => 'Montag von Set 1', '248' => 'Montag bis Set 1', '249' => 'Montag von Set 2', '250' => 'Montag bis Set 2', '251' => 'Montag von Set 3', '252' => 'Montag bis Set 3',
                '253' => 'Dienstag von Set 1', '254' => 'Dienstag bis Set 1', '255' => 'Dienstag von Set 2', '256' => 'Dienstag bis Set 2', '257' => 'Dienstag von Set 3', '258' => 'Dienstag bis Set 3',
                '259' => 'Mittwoch von Set 1', '260' => 'Mittwoch bis Set 1', '261' => 'Mittwoch von Set 2', '262' => 'Mittwoch bis Set 2', '263' => 'Mittwoch von Set 3', '264' => 'Mittwoch bis Set 3',
                '265' => 'Donnerstag von Set 1', '266' => 'Donnerstag bis Set 1', '267' => 'Donnerstag von Set 2', '268' => 'Donnerstag bis Set 2', '269' => 'Donnerstag von Set 3', '270' => 'Donnerstag bis Set 3',
                '271' => 'Freitag von Set 1', '272' => 'Freitag bis Set 1', '273' => 'Freitag von Set 2', '274' => 'Freitag bis Set 2', '275' => 'Freitag von Set 3', '276' => 'Freitag bis Set 3',
                '277' => 'Samstag von Set 1', '278' => 'Samstag bis Set 1', '279' => 'Samstag von Set 2', '280' => 'Samstag bis Set 2', '281' => 'Samstag von Set 3', '282' => 'Samstag bis Set 3'
            ];

            $position = -42; //ab dieser Position im Objektbaum
            
            foreach ($ids as $id => $name) 
            {
                $this->RegisterVariableInteger($id, $name, '~UnixTimestampTime', $position++);
                $this->getParameter($id);
                $this->GetValue($id);
                $this->EnableAction($id);
            }
        } 
        else 
        {
            $ids = 
            [
                '241', '242', '243', '244', '245', '246', '247', '248', '249', '250', '251', '252', '253', '254', '255', '256', '257', '258', '259', '260', '261', '262','263', '264',
                '265', '266', '267', '268', '269', '270', '271', '272', '273', '274', '275', '276', '277', '278', '279', '280', '281', '282'
            ];
            
            foreach ($ids as $id) 
            {
                $this->UnregisterVariable($id);
            }
        }
    }

    public function RequestAction($Ident, $Value) 
{
    $parameterMapping = [
        'HeizungVariable' => 'Heizung',
        'KuehlungVariable' => 'Kuehlung',
        'WarmwasserVariable' => 'Warmwasser',
        'WWsetVariable' => 'Wset',
        'TempsetVariable' => 'Tempset',
        '223' => '223', '224' => '224', '225' => '225', '226' => '226', '227' => '227', '228' => '228', '229' => '229', '230' => '230', '231' => '231', '232' => '232', '233' => '233',
        '234' => '234', '235' => '235', '236' => '236', '237' => '237', '238' => '238', '239' => '239', '240' => '240', '241' => '241', '242' => '242', '243' => '243', '244' => '244',
        '245' => '245', '246' => '246', '247' => '247', '248' => '248', '249' => '249', '250' => '250', '251' => '251', '252' => '252', '253' => '253', '254' => '254', '255' => '255',
        '256' => '256', '257' => '257', '258' => '258', '259' => '259', '260' => '260', '261' => '261', '262' => '262', '263' => '263', '264' => '264', '265' => '265', '266' => '266',
        '267' => '267', '268' => '268', '269' => '269', '270' => '270', '271' => '271', '272' => '272', '273' => '273', '274' => '274', '275' => '275', '276' => '276', '277' => '277',
        '278' => '278', '279' => '279', '280' => '280', '281' => '281', '282' => '282'
    ];

    if (array_key_exists($Ident, $parameterMapping)) {
        $parameterName = $parameterMapping[$Ident];
        $this->setParameter($parameterName, $Value);
        $this->getParameter($parameterName);
        $this->SendDebug("Parameter $parameterName", "Folgender Wert wird an die Funktion setParameter gesendet: $Value", 0);
    }
}
    
    public function Update()
    {
        //Verbindung zur Lux
        $IpWwc = "{$this->ReadPropertyString('IPAddress')}";
        $WwcJavaPort = "{$this->ReadPropertyInteger('Port')}";
        $SiteTitle = "WÄRMEPUMPE";

        // Namen der Variablen laden (3004 Berechnungen lesen)
        require_once __DIR__ . '/java_3004.php';

        // Lese die ID-Liste
        $idListe = json_decode($this->ReadPropertyString('IDListe'), true);

        // Socket verbinden
        $socket = socket_create(AF_INET, SOCK_STREAM, 0);
        $connect = socket_connect($socket, $IpWwc, $WwcJavaPort);

        //Debug senden
        if (!$connect) 
        {
            $error_code = socket_last_error();
            $this->SendDebug("Socketverbindung", "Verbindung zum Socket fehlerhaft: ".$IpWwc.":".$WwcJavaPort." Fehler: ".$error_code."", 0);
            $this->LogMessage("Verbindung zum Socket fehlerhaft: ".$IpWwc.":".$WwcJavaPort." Fehler: ".$error_code."", KL_ERROR);
        } 
        else 
        {
            $this->SendDebug("Socketverbindung", "Verbindung zum Socket erfolgreich: ".$IpWwc.":".$WwcJavaPort."", 0);
        }

        // Daten holen
        $msg = pack('N*',3004);
        $send=socket_write($socket, $msg, 4); //3004 senden

        $msg = pack('N*',0);
        $send=socket_write($socket, $msg, 4); //0 senden

        socket_recv($socket,$Test,4,MSG_WAITALL);  // Lesen, sollte 3004 zurückkommen
        $Test = unpack('N*',$Test);

        socket_recv($socket,$Test,4,MSG_WAITALL); // Status
        $Test = unpack('N*',$Test);

        socket_recv($socket,$Test,4,MSG_WAITALL); // Länge der nachfolgenden Werte
        $Test = unpack('N*',$Test);

        $JavaWerte = implode($Test);

        for ($i = 0; $i < $JavaWerte; ++$i)//vorwärts
        {
            socket_recv($socket,$InBuff[$i],4,MSG_WAITALL);  // Lesen, sollte 3004 zurückkommen
            $daten_raw[$i] = implode(unpack('N*',$InBuff[$i]));
        }

        socket_close($socket);

        for ($i = 0; $i < $JavaWerte; ++$i) 
        {
            
            //Hier startet der Ablauf um Werte abzugreifen, welche ohne Auswahl eine ID zur Berechnung an die Funktion gesandt werden
            if ($i == 257) //Wärmeleistung an Funktion senden zur Berechnung des COP
            {
                $value = $this->convertValueBasedOnID($daten_raw[$i], $i);
                $this->calc_cop('cop', $value);
            }  

            if ($i == 154) //Wärmeleistung an Funktion senden zur Berechnung des JAZ
            {
                $value_out = $this->convertValueBasedOnID($daten_raw[$i], $i);
                $this->calc_jaz('jaz', $value_out);
            }
            
            //Hier startet der allgemeine Ablauf zum aktualiseren der Variablen nach Auswahl der ID's durch den Anwender
            if (in_array($i, array_column($idListe, 'id'))) 
            {
        
                // Werte umrechnen wenn nötig
                $value = $this->convertValueBasedOnID($daten_raw[$i], $i);

                // Direkte Erstellung oder Aktualisierung der Variable mit Ident und Positionsnummer
                $ident = $java_dataset[$i];
                $varid = $this->CreateOrUpdateVariable($ident, $value, $i);

                // Debug senden
                $this->SendDebug("Wert gesendet", "Der Wert: ".$daten_raw[$i]." der ID: ".$i." wurde erfasst, umgerechnet in: ".$value." und an die Funktion 'CreateOrUpdateVariable' gesandt", 0);

            }   
            
            else 
            {
                // Variable löschen, da sie nicht mehr in der ID-Liste ist
                //$this->DeleteVariableIfExists($java_dataset[$i]);
                $this->UnregisterVariable($ident, $this->InstanceID);
            }
        }
    }
    
    private function convertValueBasedOnID($value, $id)
    {
        // Hier erfolgt die Konvertierung der Werte basierend auf der 'id'
        switch ($id) 
        {
            case (($id >= 10 && $id <= 14) || ($id >= 16 && $id <= 28) || $id == 122 || ($id >= 136 && $id <= 137) || ($id >= 142 && $id <= 144) || ($id >= 151 && $id <= 154) || ($id >= 175 && $id <= 179) ||$id == 183 || $id == 189 || ($id >= 194 && $id <= 200) || ($id >= 208 && $id <= 209) || ($id >= 227 && $id <= 229)):
                return round($value * 0.1, 1);
            
            case ($id == 15): //Aussentemperatur Minustest
                $minusTest = $value * 0.1;
                if ($minusTest > 429496000) 
                {
                    $value -= 4294967296;
                    $value *= 0.1; 
                } 
                else 
                {
                    $value *= 0.1; 
                }
                return round($value, 1); 

            case ($id == 56 || $id == 58 || ($id >= 60 && $id <= 77) || $id == 120 || $id == 123 || $id == 141|| $id == 158 || $id == 161): //Laufzeit umrechnen in Stunden und Minuten
                $time = $value;
                $hours = floor($time / (60 * 60));
                $time -= $hours * (60 * 60);
                $minutes = floor($time / 60);
                $time -= $minutes * 60;
                $value = "{$hours}h {$minutes}m";
                return ($value); 

            case ($id == 147 || ($id >= 156 && $id <= 157) || ($id >= 162 && $id <= 165) || ($id >= 168 && $id <= 169) || ($id >= 180 && $id <= 181) || ($id >= 187 && $id <= 188) || ($id >= 210 && $id <= 211)):
                return round($value * 0.01, 1);

            case ($id == 257):
                return round($value * 0.001, 2);
            
            default:
                return round($value * 1, 1); // Standardmäßig Konvertierung
        }
    }
            
    private function CreateOrUpdateVariable($ident, $value, $id)
    {
        // Variable erstellen und Profil zuordnen
        switch ($id) 
        {
                case (($id >= 10 && $id <= 28) || $id == 122 || $id == 136 || $id == 137 || ($id >= 142 && $id <= 144) || ($id >= 175 && $id <= 177) || $id == 189 || ($id >= 194 && $id <= 195) || ($id >= 198 && $id <= 200) || ($id >= 227 && $id <= 229)):
                    $this->RegisterVariableFloat($ident, $ident, '~Temperature', $id);
                    break;

                case (($id >= 29 && $id <= 55) || ($id >= 138 && $id <= 140) || $id == 146 || ($id >= 166 && $id <= 167) || ($id >= 170 && $id <= 171) || $id == 182 || $id == 186 || ($id >= 212 && $id <= 216)):
                    $this->RegisterVariableBoolean($ident, $ident, '~Switch', $id);
                    break;    
    
                case ($id == 56 || $id == 58 || ($id >= 60 && $id <= 77) || $id == 120 || $id == 123 || $id == 141|| $id == 158 || $id == 161):
                    $this->RegisterVariableString($ident, $ident, '', $id);
                    break;
                    
                case ($id == 57 || $id == 59):
                    $this->RegisterVariableInteger($ident, $ident, 'WPLUX.Imp', $id);
                    break;
    
                case ($id == 78):
                    $this->RegisterVariableInteger($ident, $ident, 'WPLUX.Typ', $id);
                    break;
    
                case ($id == 79):
                    $this->RegisterVariableInteger($ident, $ident, 'WPLUX.Biv', $id);
                    break;
    
                case ($id == 80):
                    $this->RegisterVariableInteger($ident, $ident, 'WPLUX.BZ', $id);
                    break;
    
                case (($id >= 95 && $id <= 99) || ($id >= 111 && $id <= 115) || $id == 134) || ($id >= 222 && $id <= 226):
                    $this->RegisterVariableInteger($ident, $ident, '~UnixTimestamp', $id);
                    break;
    
                case (($id >= 106 && $id <= 110) || ($id >= 217 && $id <= 221)):
                    $this->RegisterVariableInteger($ident, $ident, 'WPLUX.Off', $id);
                    break;
    
                case ($id == 116 || $id == 172 || $id == 174):
                    $this->RegisterVariableBoolean($ident, $ident, 'WPLUX.Comf', $id);
                    break;

                case ($id == 117):
                    $this->RegisterVariableInteger($ident, $ident, 'WPLUX.Men1', $id);
                    break;
                            
                case ($id == 118):
                    $this->RegisterVariableInteger($ident, $ident, 'WPLUX.Men2', $id);
                    break;

                case ($id == 119):
                    $this->RegisterVariableInteger($ident, $ident, 'WPLUX.Men3', $id);
                    break;

                case ($id == 124):
                    $this->RegisterVariableBoolean($ident, $ident, 'WPLUX.Akt', $id);
                    break;

                case ($id == 147 || ($id >= 156 && $id <= 157) || ($id >= 162 && $id <= 165) || ($id >= 168 && $id <= 169)):
                    $this->RegisterVariableFloat($ident, $ident, '~Volt', $id);
                    break;

                case ($id == 173):
                    $this->RegisterVariableInteger($ident, $ident, 'WPLUX.lh', $id);
                    break;
    
                case (($id >= 178 && $id <= 179) || ($id >= 196 && $id <= 197) || ($id >= 208 && $id <= 209)):
                    $this->RegisterVariableFloat($ident, $ident, '~Temperature.Difference', $id);
                    break;
    
                case (($id >= 180 && $id <= 181) || ($id >= 210 && $id <= 211)):
                    $this->RegisterVariableFloat($ident, $ident, 'WPLUX.Pres', $id);
                    break;
    
                case ($id == 183):
                    $this->RegisterVariableFloat($ident, $ident, '~Valve.F', $id);
                    break;
    
                case ($id == 184 || $id == 193  || $id == 231):
                    $this->RegisterVariableInteger($ident, $ident, 'WPLUX.Fan', $id);
                    break;
    
                case (($id >= 151 && $id <= 154)|| ($id >= 187 && $id <= 188)):
                    $this->RegisterVariableFloat($ident, $ident, '~Electricity', $id);
                    break;
    
                case ($id == 191):
                    $this->RegisterVariableInteger($ident, $ident, 'WPLUX.Bet', $id);
                    break;
    
                case ($id == 231):
                    $this->RegisterVariableFloat($ident, $ident, '~Hertz', $id);
                    break;
    
                case ($id == 257):
                    $this->RegisterVariableFloat($ident, $ident, '~Power', $id);
                    break;

                default:
                    // Standardprofil, falls keine spezifische Zuordnung gefunden wird
                    $this->RegisterVariableString($ident, $ident, '', $id);
                    break;
        }

        $this->SetValue($ident, $value);
        $this->SendDebug("Variable aktualisiert", "Variable erstellt/aktualisiert und Profil zugeordnet, ID: ".$id.", Name: ".$ident.", Wert: ".$value."", 0);
    }
    
    private function DeleteVariableIfExists($ident)
    {
        $variableID = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);
        if ($variableID !== false) 
        {
            // Variable löschen
            $this->UnregisterVariable($ident);
            
            // Debug-Ausgabe
            $this->SendDebug("Variable gelöscht", "Variable wurde gelöscht da die ID nicht mehr in der ID-Liste vorhanden ist - Variablen-ID: ".$variableID."  Name: ".$ident."", 0);       
        }
    }

    private function setParameter($type, $value) //3002 Werte senden
    {
        // IP-Adresse und Port aus den Konfigurationseinstellungen lesen
        $ipWwc = $this->ReadPropertyString('IPAddress');
        $wwcJavaPort = $this->ReadPropertyInteger('Port');

        // Verbindung zum Socket herstellen
        $socket = socket_create(AF_INET, SOCK_STREAM, 0);
        $connect = socket_connect($socket, $ipWwc, $wwcJavaPort);

        // Daten senden
        $msg = pack('N*', 3002); // 3002 senden aktivieren
        socket_write($socket, $msg, 4);

        // Parameter je nach Typ festlegen
        $parameter = 0;

        switch ($type) {
            case 'Tempset':
                $parameter = 1;
                if ($value >= -5 && $value <= 5) $value *= 10; // Wert für Temperaturkorrektur
                break;
            case 'Wset':
                $parameter = 2;
                if ($value >= 30 && $value <= 65) $value *= 10; // Wert für Warmwasserkorrektur
                break;
            case 'Heizung':
                $parameter = 3;
                break;
            case 'Warmwasser':
                $parameter = 4;
                break;
            case 'Kuehlung':
                $parameter = 108;
                $value = ($value == 0) ? 0 : 1; // Wert für Kühlung auf 0 oder 1 setzen
                break;
            case '223': case '224': case '225': case '226': case '227': case '228': case '229': case '230': case '231': case '232': case '233': case '234': case '235': case '236':
            case '237': case '238': case '239': case '240': case '241': case '242': case '243': case '244': case '245': case '246': case '247': case '248': case '249': case '250':
            case '251': case '252': case '253': case '254': case '255': case '256': case '257': case '258': case '259': case '260': case '261': case '262': case '263': case '264':
            case '265': case '266': case '267': case '268': case '269': case '270': case '271': case '272': case '273': case '274': case '275': case '276': case '277': case '278':
            case '279': case '280': case '281': case '282':
                $parameter = (int)$type;
                if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                break;
        }

        // SetParameter senden
        $msg = pack('N*', $parameter);
        socket_write($socket, $msg, 4);

        // Wert senden
        $msg = pack('N*', $value);
        socket_write($socket, $msg, 4);

        // Daten vom Socket empfangen und verarbeiten
        socket_recv($socket, $test, 4, MSG_WAITALL);  // Lesen, sollte 3002 zurückkommen
        socket_recv($socket, $test, 4, MSG_WAITALL); // Lesen, sollte Status zurückkommen

        // Socket schließen
        socket_close($socket);

        // Debug senden
        $this->SendDebug("Socketverbindung", "Der Parameter: $parameter mit dem Wert: $value wurde an den Socket gesendet", 0);
    }

    private function getParameter($mode) //3003 Werte holen
    {
        // IP-Adresse und Port aus den Konfigurationseinstellungen lesen
        $ipWwc = $this->ReadPropertyString('IPAddress');
        $wwcJavaPort = $this->ReadPropertyInteger('Port');

        // Verbindung zum Socket herstellen
        $socket = socket_create(AF_INET, SOCK_STREAM, 0);
        $connect = socket_connect($socket, $ipWwc, $wwcJavaPort);

        // Daten holen
        $msg = pack('N*', 3003); // 3003 Daten holen
        socket_write($socket, $msg, 4); //3003 senden

        $msg = pack('N*', 0);
        socket_write($socket, $msg, 4); //0 senden

        socket_recv($socket, $test, 4, MSG_WAITALL);  // Lesen, sollte 3003 zurückkommen
        socket_recv($socket, $test, 4, MSG_WAITALL); // Länge der nachfolgenden Werte
        $test = unpack('N*', $test);
        $javaWerte = implode($test);

        for ($i = 0; $i < $javaWerte; ++$i) {
            socket_recv($socket, $inBuff[$i], 4, MSG_WAITALL);
            $datenRaw[$i] = implode(unpack('N*', $inBuff[$i]));
        }

        socket_close($socket);

        // Den Wert entsprechend dem gewünschten Modus setzen
        switch ($mode) {
            case 'Heizung':
                $this->SetValue('HeizungVariable', $datenRaw[3]);
                $this->SendDebug("Modus Heizung", "Einstellung Modus Heizung: " . $datenRaw[3] . " von der Lux geholt und in Variable gespeichert", 0);
                break;
            case 'Warmwasser':
                $this->SetValue('WarmwasserVariable', $datenRaw[4]);
                $this->SendDebug("Modus Warmwasser", "Einstellung Modus Warmwasser: " . $datenRaw[4] . " von der Lux geholt und in Variable gespeichert", 0);
                break;
            case 'Kuehlung':
                $this->SetValue('KuehlungVariable', $datenRaw[108]);
                $this->SendDebug("Modus Kühlung", "Einstellung Modus Kühlung: " . $datenRaw[108] . " von der Lux geholt und in Variable gespeichert", 0);
                break;
            case 'Tempset':
                $tempSetValue = $datenRaw[1] * 0.1;
                if ($tempSetValue > 429496000) 
                {
                    $tempSetValue -= 4294967296;
                    $tempSetValue *= 0.1;
                } 
                else 
                {
                    $tempSetValue *= 0.1;
                }
                $this->SetValue('TempsetVariable', $tempSetValue);
                $this->SendDebug("Temperaturanpassung", "Wert der Temperaturanpassung: " . $tempSetValue . " von der Lux geholt und in Variable gespeichert", 0);
                break;
            case 'Wset':
                $this->SetValue('WWsetVariable', $datenRaw[2] * 0.1);
                $this->SendDebug("Warmwasser Soll", "Wert der Warmwassser Solltemperatur: " . $datenRaw[2] * 0.1 . " von der Lux geholt und in Variable gespeichert", 0);
                break;
            case '223': case '224': case '225': case '226': case '227': case '228': case '229': case '230': case '231': case '232': case '233': case '234': case '235': case '236':
            case '237': case '238': case '239': case '240': case '241': case '242': case '243': case '244': case '245': case '246': case '247': case '248': case '249': case '250':
            case '251': case '252': case '253': case '254': case '255': case '256': case '257': case '258': case '259': case '260': case '261': case '262': case '263': case '264':
            case '265': case '266': case '267': case '268': case '269': case '270': case '271': case '272': case '273': case '274': case '275': case '276': case '277': case '278':
            case '279': case '280': case '281': case '282':
                $weekModeValue = $datenRaw[(int)$mode] - 3600;  // Unix-Zeit korrigieren
                $this->SetValue($mode, $weekModeValue);
                break;
        }
    }

    private function calc_cop($mode, $value) //COP berechnen
    {
        $copfaktorVariableID = @$this->GetIDForIdent('copfaktor');
        $copVisible = $this->ReadPropertyFloat('kwin');
        
        if ($mode == 'cop' && $copVisible !== 0 && IPS_VariableExists($copVisible) && $copfaktorVariableID !== false)
            {
                $kw_in = GetValue($this->ReadPropertyFloat('kwin'));
                $cop = $value / $kw_in;
                $this->SetValue('copfaktor', $cop);
                
                $this->SendDebug("COP-Faktor", "Faktor: ".$cop." wurde berechnet anhand der Eingangsleistung: ".$kw_in." und Wärmeleistung: ".$value."", 0);
            }
    }

    private function calc_jaz(string $mode, float $value_out) //JAZ berechnen
    {
        $jazVisible = $this->ReadPropertyFloat('kwhin');
        $jazfaktorVariableID = @$this->GetIDForIdent('jazfaktor');
    
        if ($mode == 'jaz' && $jazVisible !== 0 && IPS_VariableExists($jazVisible) && $jazfaktorVariableID !== false)
        {
            $kwh_in = GetValue($this->ReadPropertyFloat('kwhin'));
    
            $this->SendDebug("JAZ-Berechnung", "Berechnungsgrundlagen eingegangen: Verbrauch (zum Zeitpunkt des Reset): ".$this->ReadAttributeFloat('start_kwh_in')." kWh, Produktion (zum Zeitpunkt des Reset): ".$this->ReadAttributeFloat('start_value_out')." kWh, Verbrauchs (gesamt): ".$kwh_in." kWh, Produktion (gesamt): ".$value_out." kWh", 0);
            
            if ($this->ReadAttributeFloat('start_kwh_in') == 0 || $this->ReadAttributeFloat('start_value_out') == 0) //Erstmalige Synchronisatiin bei Startwert 0
            {
                $this->WriteAttributeFloat('start_kwh_in', $kwh_in);
                $this->WriteAttributeFloat('start_value_out', $value_out);
            
                $this->SendDebug("JAZ-Synch", "Die Variabeln wurden synchronisiert (sollte nur einmalig nach dem Reset passieren)", 0);
            }

            $kwh_in_Change = $kwh_in - $this->ReadAttributeFloat('start_kwh_in');
            $value_out_Change = $value_out - $this->ReadAttributeFloat('start_value_out');
    
            if ($kwh_in_Change != 0) // Überprüfen, ob der Wert von $kwh_in_Change nicht 0 ist, um eine Division durch 0 zu verhindern
            {
                $jaz = $value_out_Change / $kwh_in_Change;
                $this->SetValue('jazfaktor', $jaz);
                $this->SendDebug("JAZ-Faktor", "Faktor: ".$jaz." wurde berechnet anhand des Energieverbrauchs (seit Reset): ".$kwh_in_Change." kWh und der Energieproduktion (seit Reset): ".$value_out_Change." kWh", 0);
            }
            else 
            {
                $this->SetValue('jazfaktor', 0);
                $this->SendDebug("JAZ-Faktor", "JAZ-Faktor konnte noch nicht berechnet werden da sich der Wert der Energieversorgung noch nicht geändert hat seit dem Reset", 0);
            } 
        }
    }

    public function reset_jaz() //Startwerte der JAZ-Berechnung zurücksetzen
    {
        $this->WriteAttributeFloat('start_kwh_in', 0);
        $this->WriteAttributeFloat('start_value_out', 0);
        $this->SendDebug("JAZ-Reset", "Der Reset der Start-Werte zur JAZ-Berechnung wurde durchgeführt", 0);
    }
}