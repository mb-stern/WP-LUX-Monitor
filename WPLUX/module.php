<?php

class WPLUX extends IPSModule
{
    private $updateTimer;
    protected static $start_kwh_in;
    protected static $start_value_out;

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
            //$this->RegisterVariableFloat('start_value_out', 'Startwert Energie Out', '', 6);
            //$this->RegisterVariableFloat('start_kwh_in', 'Startwert Energie In', '', 6);
            $this->start_kwh_in = null;
            $this->start_value_out = null;
        } 
        else 
        {
            $this->UnregisterVariable('jazfaktor');
            //$this->UnregisterVariable('start_value_out');
            //$this->UnregisterVariable('start_kwh_in');
        }
    }

    public function RequestAction($Ident, $Value) 
    {

        // Überprüfe, ob der Wert der Steuervariablen geändert hat und senden an die Funktion sendDataToSocket
        if ($Ident == 'HeizungVariable') 
        {
            // Rufe die Funktion auf und übergebe den neuen Wert
            $this->sendDataToSocket('Heizung', $Value);
            $this->getParameter('Warmwasser');
            $this->SendDebug("Heizfunktion", "Folgender Wert wird an die Funktion sendDataToSocket gesendet: ".$Value."", 0);   
        }
    
        // Überprüfe, ob die Aktion von der 'KuehlungVariable' ausgelöst wurde
        if ($Ident == 'KuehlungVariable') 
        {
            // Rufe die Funktion auf und übergebe den neuen Wert
            $this->sendDataToSocket('Kuehlung', $Value);
            $this->getParameter('Kuehlung');
            $this->SendDebug("Kühlfunktion", "Folgender Wert wird an die Funktion sendDataToSocket gesendet: ".$Value."", 0); 
        }
    
        // Überprüfe, ob die Aktion von der 'WarmwasserVariable' ausgelöst wurde
        if ($Ident == 'WarmwasserVariable') 
        {
            // Rufe die Funktion auf und übergebe den neuen Wert
            $this->sendDataToSocket('Warmwasser', $Value);
            $this->getParameter('Warmwasser');
            $this->SendDebug("Warmwasserfunktion", "Folgender Wert wird an die Funktion sendDataToSocket gesendet: ".$Value."", 0);  
        }
        if ($Ident == 'WWsetVariable') 
        {
            // Rufe die Funktion auf und übergebe den neuen Wert
            $this->sendDataToSocket('Wset', $Value);
            $this->getParameter('Wset');
            $this->SendDebug("Warmwasseranpassung", "Folgender Wert wird an die Funktion sendDataToSocket gesendet: ".$Value."", 0);   
        }
        if ($Ident == 'TempsetVariable') 
        {
            // Rufe die Funktion auf und übergebe den neuen Wert
            $this->sendDataToSocket('Tempset', $Value);
            $this->getParameter('Tempset');
            $this->SendDebug("Temperaturanpassung", "Folgender Wert wird an die Funktion sendDataToSocket gesendet: ".$Value."", 0);   
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

                //Debug senden
                $this->SendDebug("Wärmemenge", "Für die COP-Berechnung wurde ID: " . $i . " erfasst und der Wert: ". $value ." an die Funktion 'calc_cop' gesendet", 0);
            }  

            if ($i == 154) //Wärmeleistung an Funktion senden zur Berechnung des COP
            {
                $value_out = $this->convertValueBasedOnID($daten_raw[$i], $i);
                $this->calc_jaz('jaz', $value_out); 

                //Debug senden
                $this->SendDebug("Energiemenge", "Für die JAZ-Berechnung wurde ID: " . $i . " erfasst und der Wert: ". $value_out ." an die Funktion 'calc_jaz' gesendet", 0);
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
                $this->DeleteVariableIfExists($java_dataset[$i]);
            }
        }
    }
    
    private function convertValueBasedOnID($value, $id)
    {
        // Hier erfolgt die Konvertierung der Werte basierend auf der 'id'
        switch ($id) 
        {
            case (($id >= 10 && $id <= 14) || ($id >= 16 && $id <= 28) || $id == 122 || ($id >= 136 && $id <= 137) || ($id >= 142 && $id <= 144) || ($id >= 175 && $id <= 179) ||$id == 183 || $id == 189 || ($id >= 194 && $id <= 200) || ($id >= 208 && $id <= 209) || ($id >= 227 && $id <= 229)):
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

            case ($id == 56 || $id == 58 || ($id >= 60 && $id <= 77) || $id == 120 || $id == 123 || $id == 141|| $id == 158 || $id == 161): //Korrektur Laufzeit und umrechnen in Stunden und Minuten
                $time = $value;
                $hours = floor($time / (60 * 60));
                $time -= $hours * (60 * 60);
                $minutes = floor($time / 60);
                $time -= $minutes * 60;
                $value = "{$hours}h {$minutes}m";
                return ($value); 

            case ($id == 147 || ($id >= 151 && $id <= 154) || ($id >= 156 && $id <= 157) || ($id >= 162 && $id <= 165) || ($id >= 168 && $id <= 169) || ($id >= 180 && $id <= 181) || ($id >= 187 && $id <= 188) || ($id >= 210 && $id <= 211)):
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

    private function sendDataToSocket($type, $value)
    {
        // IP-Adresse und Port aus den Konfigurationseinstellungen lesen
        $ipWwc = $this->ReadPropertyString('IPAddress');
        $wwcJavaPort = $this->ReadPropertyInteger('Port');

        // Verbindung zum Socket herstellen
        $socket = socket_create(AF_INET, SOCK_STREAM, 0);
        $connect = socket_connect($socket, $ipWwc, $wwcJavaPort);

        // Daten senden
        $msg = pack('N*', 3002); // 3002 senden aktivieren
        $send = socket_write($socket, $msg, 4);

        // Parameter je nach Typ festlegen
        switch ($type) {
            case 'Tempset':
                $parameter = 1;
                break;
            case 'Wset':
                $parameter = 2;
                break;
            case 'Heizung':
                $parameter = 3;
                break;
            case 'Warmwasser':
                $parameter = 4;
                break;
            case 'Kuehlung':
                $parameter = 108;
                break;
            default:
                $parameter = 0;
                break;
        }

        // SetParameter senden
        $msg = pack('N*', $parameter);
        $send = socket_write($socket, $msg, 4);

        // Auswahl senden
        switch ($type) 
        {
            case 'Kuehlung':
                $value = ($value == 0) ? 0 : 1; // Wert für Kühlung auf 0 oder 1 setzen
                break;
            case 'Tempset':
                if ($value >= -5 && $value <= 5) // Wert für Temperaturkorrektur
                {
                    $value *= 10; 
                }
                break;
            case 'Wset':
                if ($value >= 30 && $value <= 65) // Wert für Warmwasserkorrektur
                {
                    $value *= 10; 
                }
                break;
            default:
                // Fallback auf 0, wenn der Wert nicht innerhalb des erwarteten Bereichs liegt
                $value = ($value >= 0 && $value <= 4) ? $value : 0;
                break;
        }

        //Debug senden
        $this->SendDebug("Socketverbindung", "Der Parameter: ".$parameter." mit dem Wert: ".$value." wurde an den Socket gesendet", 0);

        $msg = pack('N*', $value); // Wert packen
        $send = socket_write($socket, $msg, 4); // Daten senden

        // Daten vom Socket empfangen und verarbeiten
        socket_recv($socket, $test, 4, MSG_WAITALL);  // Lesen, sollte 3002 zurückkommen
        $test = unpack('N*', $test);

        socket_recv($socket, $test, 4, MSG_WAITALL); // Lesen, sollte Status zurückkommen
        $test = unpack('N*', $test);

        // Socket schließen
        socket_close($socket);
    }

    private function getParameter($mode)
    {

        // IP-Adresse und Port aus den Konfigurationseinstellungen lesen
        $ipWwc = $this->ReadPropertyString('IPAddress');
        $wwcJavaPort = $this->ReadPropertyInteger('Port');

        // Verbindung zum Socket herstellen
        $socket = socket_create(AF_INET, SOCK_STREAM, 0);
        $connect = socket_connect($socket, $ipWwc, $wwcJavaPort);

        // Daten holen
        $msg = pack('N*', 3003); // 3003 Daten holen
        $send = socket_write($socket, $msg, 4); //3003 senden

        $msg = pack('N*',0);
        $send=socket_write($socket, $msg, 4); //0 senden

        socket_recv($socket,$Test,4,MSG_WAITALL);  // Lesen, sollte 3003 zurückkommen
        $Test = unpack('N*',$Test);
        
        socket_recv($socket,$Test,4,MSG_WAITALL); // Länge der nachfolgenden Werte
        $Test = unpack('N*',$Test);
        
        $JavaWerte = implode($Test);

        for ($i = 0; $i < $JavaWerte; ++$i)
        {
            socket_recv($socket,$InBuff[$i],4,MSG_WAITALL);
            $daten_raw[$i] = implode(unpack('N*',$InBuff[$i]));
        }
        
        socket_close($socket);
        
        for ($i = 0; $i < $JavaWerte; ++$i)
        {
            if ($mode == 'Heizung' && $i == 3) // Betriebsart Heizung
            {
                $this->SetValue('HeizungVariable', $daten_raw[$i]);
                $this->SendDebug("Modus Heizung", "Einstellung Modus Heizung: ".$daten_raw[$i]." von der Lux geholt und in Variable gespeichert", 0);
            }
            elseif ($mode == 'Warmwasser' && $i == 4) // Betriebsart Warmwasser
            {
                $this->SetValue('WarmwasserVariable', $daten_raw[$i]);
                $this->SendDebug("Modus Warmwasser", "Einstellung Modus Warmwasser: ".$daten_raw[$i]." von der Lux geholt und in Variable gespeichert", 0);
            }
            elseif ($mode == 'Kuehlung' && $i == 108) // Betriebsart Kühlung
            {
                $this->SetValue('KuehlungVariable', $daten_raw[$i]);
                $this->SendDebug("Modus Kühlung", "Einstellung Modus Kühlung: ".$daten_raw[$i]." von der Lux geholt und in Variable gespeichert", 0);
            }
            elseif ($mode == 'Tempset' && $i == 1) // Temperaturanpassung
            {
                $minusTest = $daten_raw[$i] * 0.1;
                if ($minusTest > 429496000) 
                {
                    $daten_raw[$i] -= 4294967296;
                    $daten_raw[$i] *= 0.1; 
                } 
                else 
                {
                    $daten_raw[$i] *= 0.1; 
                }
                $this->SetValue('TempsetVariable', $daten_raw[$i]);
                $this->SendDebug("Temperaturanpassung", "Wert der Temperaturanpassung: ".$daten_raw[$i]." von der Lux geholt und in Variable gespeichert", 0);
            }
            elseif ($mode == 'Wset' && $i == 2) // Warmwasseranpassung
            {
                $this->SetValue('WWsetVariable', $daten_raw[$i] * 0.1);
                $this->SendDebug("Warmwasser Soll", "Wert der Warmwassser Solltemperatur: ".$daten_raw[$i] * 0.1." von der Lux geholt und in Variable gespeichert", 0);
            }
        }
    }

    private function calc_cop($mode, $value)
    {
        //Berechnung des COP-Faktors
        $copfaktorVariableID = @$this->GetIDForIdent('copfaktor');
        $copVisible = $this->ReadPropertyFloat('kwin');
        if ($mode == 'cop' && $copVisible !== 0 && IPS_VariableExists($copVisible) && $copfaktorVariableID !== false)
            {
                $kw_in = GetValue($this->ReadPropertyFloat('kwin'));
                $cop = $value / $kw_in;
                $this->SetValue('copfaktor', $cop);
                
                $this->SendDebug("COP", "Der COP-Faktor: ".$cop." wurde durch die Funktion 'calc_cop' berechnet anhand der Eingangsleistung: ".$kw_in." und Wärmeleistung: ".$value." und in die Variable ausgegeben", 0);
            }
    }

    private function calc_jaz(string $mode, float $value_out)
    {
        // Berechnung des JAZ-Faktors
        $jazVisible = $this->ReadPropertyFloat('kwhin');
        $jazfaktorVariableID = @$this->GetIDForIdent('jazfaktor');
    
        if ($mode == 'jaz' && $jazVisible !== 0 && IPS_VariableExists($jazVisible) && $jazfaktorVariableID !== false)
        {
            $kwh_in = GetValue($this->ReadPropertyFloat('kwhin'));

            $this->SendDebug("JAZ", "Variablen zur Berechnung: start_kwh_in: ".WPLUX::$start_kwh_in." start_value_out: ".WPLUX::$start_value_out." kWh_in: ".$kwh_in." value_out: ".$value_out."", 0);
            
            // Überprüfen, ob die Instanzvariablen bereits initialisiert wurden
            if (WPLUX::$start_kwh_in === null || WPLUX::$start_value_out === null)
            {
                // Initialisierung der Instanzvariablen
                WPLUX::$start_kwh_in = $kwh_in;
                WPLUX::$start_value_out = $value_out;
                $this->SendDebug("JAZ", "Variablen wurden abgeglichen (sollte nur einmalig passieren)", 0);
            }

            $kwh_in_Change = $kwh_in - WPLUX::$start_kwh_in;
            $value_out_Change = $value_out - WPLUX::$start_value_out;
    
            if ($kwh_in_Change != 0) // Überprüfen, ob der Wert von $kwh_in_Change nicht 0 ist, um eine Division durch 0 zu verhindern
            {
                $jaz = $value_out_Change / $kwh_in_Change;
                $this->SetValue('jazfaktor', $jaz);
                $this->SendDebug("JAZ", "JAZ-Faktor: ".$jaz." wurde durch 'calc_jaz' berechnet (seit Reset)): Eingangsenergie: ".$kwh_in_Change." und Ausgangsenergie: ".$value_out_Change." und in die Variable ausgegeben", 0);
            }
            else 
            {
                $this->SetValue('jazfaktor', 0);
                $this->SendDebug("JAZ", "Der JAZ-Faktor konnte noch nicht berechnet werden da die Wertänderung noch nicht stattgefunden hat", 0);
            } 
        }
    }
}