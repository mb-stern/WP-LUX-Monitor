<?php

class WPLUXSymcon extends IPSModule
{
    private $updateTimer;

    protected function Log($Message)
    {
        IPS_LogMessage(__CLASS__, $Message);
    }

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        //Variableprofile erstellen
        require_once __DIR__ . '/variable_profile.php';

        $this->RegisterPropertyString('IPAddress', '192.168.178.59');
        $this->RegisterPropertyInteger('Port', 8889);
        $this->RegisterPropertyString('IDListe', '[]');
        $this->RegisterPropertyInteger('UpdateInterval', 0);
        
        $this->RegisterPropertyInteger('Heizung', 0);
        $this->RegisterPropertyInteger('Kuehlung', 0);
        $this->RegisterPropertyInteger('Warmwasser', 0); 
        
        $this->RegisterPropertyBoolean('HeizungVisible', false);
        $this->RegisterPropertyBoolean('KuehlungVisible', false);
        $this->RegisterPropertyBoolean('WarmwasserVisible', false);

        // Timer für Aktualisierung registrieren
        $this->RegisterTimer('UpdateTimer', 0, 'WPLUX_Update(' . $this->InstanceID . ');');  
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
    
        // Überprüfen, ob die erforderlichen Konfigurationsparameter gesetzt sind
        $ipAddress = $this->ReadPropertyString('IPAddress');
        $port = $this->ReadPropertyInteger('Port');
        $idListe = json_decode($this->ReadPropertyString('IDListe'), true);
    
        if ($ipAddress && $port && !empty($idListe)) 
        {
            // Timer für Aktualisierung aktualisieren
            $this->SetTimerInterval('UpdateTimer', $this->ReadPropertyInteger('UpdateInterval') * 1000);
    
            // Bei Änderungen am Konfigurationsformular oder bei der Initialisierung auslösen
            $this->Update();

            // Den Wert der Heizungsvariable lesen und an die Funktion senden
            $heizungValue = $this->ReadPropertyInteger('Heizung');
            $this->SendDebug("Heizfunktion", "Folgender Wert wird an die Funktion gesendet: ".$heizungValue."", 0);
            $this->sendDataToSocketHeizung($heizungValue);

            // Den Wert der Warmwasservariable lesen und an die Funktion senden
            $warmwasserValue = $this->ReadPropertyInteger('Warmwasser');
            $this->SendDebug("Warmwasserfunktion", "Folgender Wert wird an die Funktion gesendet: ".$warmwasserValue."", 0);
            $this->sendDataToSocketWarmwasser($warmwasserValue);

            /*
            // Den Wert der Kühlungsvariable lesen und an die Funktion senden
            $kuehlungValue = $this->ReadPropertyInteger('Kuehlung');
            $this->SendDebug("Kühlfunktion", "Folgender Wert wird an die Funktion gesendet: ".$kuehlungValue."", 0);
            $this->sendDataToSocketKuehlung($kuehlungValue);
            */
        } 
        else 
        {
            // Erforderliche Konfigurationsparameter fehlen, hier kannst du ggf. eine Warnung ausgeben
            $this->SendDebug("Konfigurationsfehler", "Erforderliche Konfigurationsparameter fehlen.", 0);
        }

            // Überprüfen, ob die Checkboxen zum erstellen der Variablen aktiviert sind
            $heizungVisible = $this->ReadPropertyBoolean('HeizungVisible');
            $kuehlungVisible = $this->ReadPropertyBoolean('KuehlungVisible');
            $warmwasserVisible = $this->ReadPropertyBoolean('WarmwasserVisible');

            // Variablen erstellen und mit Initialwerten versehen
            if ($heizungVisible) {
                $this->RegisterVariableInteger('HeizungVariable', 'Heizung', '~Valve');
                $this->SetValue('HeizungVariable', $this->ReadPropertyInteger('Heizung'));
            } else {
                $this->UnregisterVariable('HeizungVariable');
            }

            if ($kuehlungVisible) {
                $this->RegisterVariableInteger('KuehlungVariable', 'Kühlung', '~Valve');
                //$this->SetValue('KuehlungVariable', $this->ReadPropertyInteger('Kuehlung'));
                $kuehlungValue = $this->GetValue('KuehlungVariable');
                $this->SendDebug("Kühlfunktion", "Folgender Wert wird an die Funktion gesendet: ".$kuehlungValue."", 0);
                $this->sendDataToSocketKuehlung($kuehlungValue);
            } else {
                $this->UnregisterVariable('KuehlungVariable');
            }

            if ($warmwasserVisible) {
                $this->RegisterVariableInteger('WarmwasserVariable', 'Warmwasser', '~Valve');
                $this->SetValue('WarmwasserVariable', $this->ReadPropertyInteger('Warmwasser'));
            } 
            else 
            {
                $this->UnregisterVariable('WarmwasserVariable');
            }
    }
    
    public function Update()
    {
        //Verbindung zur Lux
        $IpWwc = "{$this->ReadPropertyString('IPAddress')}";
        $WwcJavaPort = "{$this->ReadPropertyInteger('Port')}";
        $SiteTitle = "WÄRMEPUMPE";

        //Debug senden
        $this->SendDebug("Verbindungseinstellung im Config", "".$IpWwc.":".$WwcJavaPort."", 0);

        // Namen der Variablen laden
        require_once __DIR__ . '/java_daten.php';

        // Lese die ID-Liste
        $idListe = json_decode($this->ReadPropertyString('IDListe'), true);

        // Socket verbinden
        $socket = socket_create(AF_INET, SOCK_STREAM, 0);
        $connect = socket_connect($socket, $IpWwc, $WwcJavaPort);

        //Debug senden
        if (!$connect) 
        {
            $error_code = socket_last_error();
            $this->SendDebug("Verbindung zum Socket fehlgeschlagen. Error:", "$error_code", 0);
        } 
        else 
        {
            $this->SendDebug("Verbindung zum Socket erfolgreich", "".$IpWwc.":".$WwcJavaPort."", 0);
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

        //socket wieder schließen
        socket_close($socket);

        // Werte anzeigen
        for ($i = 0; $i < $JavaWerte; ++$i) 
        {
            if (in_array($i, array_column($idListe, 'id'))) 
            {
        
                // Werte umrechnen wenn nötig
                $value = $this->convertValueBasedOnID($daten_raw[$i], $i);

                // Debug senden
                $this->SendDebug("Wert empfangen", "Der Wert: ".$daten_raw[$i]." der ID: ".$i." wurde von der WP empfangen, umgerechnet in: ".$value." und in eine Variable ausgegeben", 0);

                // Direkte Erstellung oder Aktualisierung der Variable mit Ident und Positionsnummer
                $ident = $java_dataset[$i];
                $varid = $this->CreateOrUpdateVariable($ident, $value, $i);
            }   else 
            {
                // Variable löschen, da sie nicht mehr in der ID-Liste ist
                $this->DeleteVariableIfExists($java_dataset[$i]);
            }
        }
    }
                
    private function AssignVariableProfilesAndType($varid, $id)
    {
        // Hier erfolgt die Zuordnung des Variablenprofils und -typs basierend auf der 'id'
        switch (true) 
        {

            case (($id >= 10 && $id <= 28) || $id == 122 || $id == 136 || $id == 137 || ($id >= 142 && $id <= 144) || ($id >= 175 && $id <= 177) || $id == 189 || ($id >= 194 && $id <= 195) || ($id >= 198 && $id <= 200) || ($id >= 227 && $id <= 229)):
            if ($varid) 
            {
                IPS_SetVariableCustomProfile($varid, '~Temperature');
            }
            return 2; // Float-Typ
                
            case (($id >= 29 && $id <= 55) || ($id >= 138 && $id <= 140) || $id == 146 || ($id >= 166 && $id <= 167) || ($id >= 170 && $id <= 171) || $id == 182 || $id == 186 || ($id >= 212 && $id <= 216)):
            if ($varid) 
            {
                IPS_SetVariableCustomProfile($varid, '~Switch');
            }
            return 0; // Boolean-Typ

            case ($id == 56 || $id == 58 || ($id >= 60 && $id <= 77) || $id == 120 || $id == 123 || $id == 141):
            if ($varid) 
            {
                IPS_SetVariableCustomProfile($varid, 'WPLUX.Sec');
            }
            return 1; // Integer
                
            case ($id == 57 || $id == 59):
            if ($varid) 
            {
                IPS_SetVariableCustomProfile($varid, 'WPLUX.Imp');
            }
            return 1; // Integer

            case ($id == 78):
            if ($varid) 
            {
                IPS_SetVariableCustomProfile($varid, 'WPLUX.Typ');
            }
            return 1; // Integer

            case ($id == 79):
            if ($varid) 
            {
                IPS_SetVariableCustomProfile($varid, 'WPLUX.Biv');
            }
            return 1; // Integer

            case ($id == 80):
            if ($varid > 0) 
            {
                IPS_SetVariableCustomProfile($varid, 'WPLUX.BZ');
            }
            return 1; // Integer

            case (($id >= 95 && $id <= 99) || ($id >= 111 && $id <= 115) || $id == 134) || ($id >= 222 && $id <= 226):
            if ($varid) 
            {
                IPS_SetVariableCustomProfile($varid, '~UnixTimestamp');
            }
            return 1; // Integer

            case (($id >= 106 && $id <= 110) || ($id >= 217 && $id <= 221)):
            if ($varid) 
            {
                IPS_SetVariableCustomProfile($varid, 'WPLUX.Off');
            }
            return 1; // Integer

            case ($id == 116 || $id == 172 || $id == 174):
            if ($varid) 
            {
                IPS_SetVariableCustomProfile($varid, 'WPLUX.Comf');
            }
            return 0; // Boolean-Typ

            case ($id == 117):
            if ($varid) 
            {
                IPS_SetVariableCustomProfile($varid, 'WPLUX.Men1');
            }
            return 1; // Integer
                        
            case ($id == 118):
            if ($varid) 
            {
                IPS_SetVariableCustomProfile($varid, 'WPLUX.Men2');
            }
            return 1; // Integer

            case ($id == 119):
            if ($varid) 
            {
                IPS_SetVariableCustomProfile($varid, 'WPLUX.Men3');
            }
            return 1; // Integer

            case ($id == 124):
            if ($varid) 
            {
                IPS_SetVariableCustomProfile($varid, 'WPLUX.Akt');
            }
            return 0; // Boolean-Typ

            case ($id == 147 || ($id >= 156 && $id <= 157) || ($id >= 162 && $id <= 165) || ($id >= 168 && $id <= 169)):
            if ($varid) 
            {
                IPS_SetVariableCustomProfile($varid, '~Volt');
            }
            return 2; // Float-Typ

            case ($id == 173):
            if ($varid) 
            {
                IPS_SetVariableCustomProfile($varid, 'WPLUX.lh');
            }
            return 1; // Integer

            case (($id >= 178 && $id <= 179) || ($id >= 196 && $id <= 197) || ($id >= 208 && $id <= 209)):
            if ($varid) 
            {
                IPS_SetVariableCustomProfile($varid, '~Temperature.Difference');
            }
            return 2; // Float-Typ

            case (($id >= 180 && $id <= 181) || ($id >= 210 && $id <= 211)):
            if ($varid) 
            {
                IPS_SetVariableCustomProfile($varid, 'WPLUX.Pres');
            }
            return 2; // Float-Typ

            case ($id == 183):
            if ($varid) 
            {
                IPS_SetVariableCustomProfile($varid, '~Valve.F');
            }
            return 2; // Float-Typ

            case ($id == 184 || $id == 193  || $id == 231):
            if ($varid) 
            {
                IPS_SetVariableCustomProfile($varid, 'WPLUX.Fan');
            }
            return 1; // Integer

            case (($id >= 151 && $id <= 154)|| ($id >= 187 && $id <= 188)  || $id == 257):
            if ($varid) 
            {
                IPS_SetVariableCustomProfile($varid, '~Electricity');
            }
            return 2; // Float-Typ

            case ($id == 191):
            if ($varid) 
            {
                IPS_SetVariableCustomProfile($varid, 'WPLUX.Bet');
            }
            return 1; // Integer

            case ($id == 231):
            if ($varid) 
            {
                IPS_SetVariableCustomProfile($varid, '~Hertz');
            }
            return 2; // Float
 
            default:
            // Standardprofil, falls keine spezifische Zuordnung gefunden wird
            if ($varid > 0) 
            {
                IPS_SetVariableCustomProfile($varid, '');
            }
            return 1; // Standardmäßig Integer-Typ
        }
    }
    
    private function convertValueBasedOnID($value, $id)
    {
        // Hier erfolgt die Konvertierung des Werts basierend auf der 'id'
        switch ($id) 
        {
        
            case (($id >= 10 && $id <= 14) || ($id >= 16 && $id <= 28) || $id == 122 || ($id >= 136 && $id <= 137) || ($id >= 142 && $id <= 144) || ($id >= 175 && $id <= 179) || $id == 189 || ($id >= 194 && $id <= 200) || ($id >= 208 && $id <= 209) || ($id >= 227 && $id <= 229)):
            return round($value * 0.1, 1);
            
            case ($id == 15):    //Aussentemperatur Minustest
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

            case ($id == 147 || ($id >= 151 && $id <= 154) || ($id >= 156 && $id <= 157) || ($id >= 162 && $id <= 165) || ($id >= 168 && $id <= 169) || ($id >= 180 && $id <= 181) || $id == 183 || ($id >= 187 && $id <= 188) || ($id >= 210 && $id <= 211)):
            return round($value * 0.01, 1);

            case ($id == 257):
            return round($value * 0.001, 1);
            
            default:
            return round($value * 1, 1); // Standardmäßig Konvertierung
        }
    }
            
    private function CreateOrUpdateVariable($ident, $value, $id)
    {
        // Überprüfen, ob die Variable bereits existiert
        $existingVarID = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);

        if ($existingVarID === false) 
        {
            // Variable existiert nicht, also erstellen
            $varid = IPS_CreateVariable($this->AssignVariableProfilesAndType(null, $id));
            IPS_SetParent($varid, $this->InstanceID);
            IPS_SetIdent($varid, $ident);
            IPS_SetName($varid, $ident);
            SetValue($varid, $value);
            IPS_SetPosition($varid, $id);

            //Debug senden
            $this->SendDebug("Variable erstellt", "Variable wurde erstellt da sie noch nicht existiert - ID: ".$id."  Variablen-ID: ".$varid."  Name: ".$ident."  Wert: ".$value."", 0);

            // Hier die Methode aufrufen, um das Profil zuzuweisen
            $this->AssignVariableProfilesAndType($varid, $id);
        } 
        else 
        {
            // Variable existiert, also aktualisieren
            $varid = $existingVarID;
            // Überprüfen, ob der Variablentyp stimmt
            if (IPS_GetVariable($varid)['VariableType'] != $this->AssignVariableProfilesAndType($varid, $id)) {
            // Variablentyp stimmt nicht überein, also Variable neu erstellen
            IPS_DeleteVariable($varid);
            $varid = IPS_CreateVariable($this->AssignVariableProfilesAndType(null, $id));
            IPS_SetParent($varid, $this->InstanceID);
            IPS_SetIdent($varid, $ident);
            IPS_SetName($varid, $ident);
            SetValue($varid, $value);
            IPS_SetPosition($varid, $id);

            //Debug senden
            $this->SendDebug("Variable erneut erstellt", "Variabletyp stimmt nicht überein, daher Variable gelöscht und erneut erstellt - ID: ".$id.", Variablen-ID: ".$varid.", Name: ".$ident.", Wert: ".$value."", 0);

            } 
            else 
            {
                // Variablentyp stimmt überein, also nur Wert aktualisieren
                SetValue($varid, $value);

                //Debug senden
                $this->SendDebug("Variable aktualisiert", "Variablentyp stimmt überein, daher wird nur der Wert aktualisiert - ID: ".$id."  Variablen-ID: ".$varid."  Name: ".$ident."  Wert: ".$value."", 0);
            }
        }
        return $varid;
    }

    private function DeleteVariableIfExists($ident)
    {
        $variableID = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);
        if ($variableID !== false) 
        {
        
            // Debug-Ausgabe
            $this->SendDebug("Variable gelöscht", "Variable wurde gelöscht da die ID nicht mehr in der ID-Liste vorhanden ist - Variablen-ID: ".$variableID."  Name: ".$ident."", 0);
                
            // Variable löschen
            IPS_DeleteVariable($variableID);
        }
    }

    private function sendDataToSocketHeizung($heizungValue)
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

        //SetParameter senden;
        $msg = pack('N*',3); //Parameter: 3: Heizung Betriebsart
        $send=socket_write($socket, $msg, 4);

        //$this->SendDebug("Parameter für Heizfunktion", "".$socket.":".$msg."", 0);

        // Auswahl senden
        switch ($heizungValue)
        {
            case 0:
                $msg = pack('N*', 0); // Auto
                break;
            case 1:
                $msg = pack('N*', 1); // Zus. Wärmeerzeugung
                break;
            case 2:
                $msg = pack('N*', 2); // Party
                break;
            case 3:
                $msg = pack('N*', 3); // Ferien
                break;
            case 4:
                $msg = pack('N*', 4); // Off
                break;
            default:
                // Fallback auf einen Standardwert, falls der Wert außerhalb des erwarteten Bereichs liegt
                $msg = pack('N*', 0); // Auto
                break;
        }

        // Daten senden
        $send = socket_write($socket, $msg, 4);

        // Daten vom Socket empfangen und verarbeiten
        socket_recv($socket, $test, 4, MSG_WAITALL);  // Lesen, sollte 3002 zurückkommen
        $test = unpack('N*', $test);

        socket_recv($socket, $test, 4, MSG_WAITALL); // Lesen, sollte Status zurückkommen
        $test = unpack('N*', $test);

        // Socket schließen
        socket_close($socket);
    }

    private function sendDataToSocketWarmwasser($warmwasserValue)
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

        //SetParameter senden;
        $msg = pack('N*',4); //Parameter: 3: Heizung Betriebsart
        $send=socket_write($socket, $msg, 4);

        //$this->SendDebug("Parameter für Heizfunktion", "".$socket.":".$msg."", 0);

        // Auswahl senden
        switch ($warmwasserValue)
        {
            case 0:
                $msg = pack('N*', 0); // Auto
                break;
            case 1:
                $msg = pack('N*', 1); // Zus. Wärmeerzeugung
                break;
            case 2:
                $msg = pack('N*', 2); // Party
                break;
            case 3:
                $msg = pack('N*', 3); // Ferien
                break;
            case 4:
                $msg = pack('N*', 4); // Off
                break;
            default:
                // Fallback auf einen Standardwert, falls der Wert außerhalb des erwarteten Bereichs liegt
                $msg = pack('N*', 0); // Auto
                break;
        }

        // Daten senden
        $send = socket_write($socket, $msg, 4);

        // Daten vom Socket empfangen und verarbeiten
        socket_recv($socket, $test, 4, MSG_WAITALL);  // Lesen, sollte 3002 zurückkommen
        $test = unpack('N*', $test);

        socket_recv($socket, $test, 4, MSG_WAITALL); // Lesen, sollte Status zurückkommen
        $test = unpack('N*', $test);

        // Socket schließen
        socket_close($socket);
    }

    private function sendDataToSocketKuehlung($kuehlungValue)
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

        //SetParameter senden;
        $msg = pack('N*',108); //Parameter: 3: Heizung Betriebsart
        $send=socket_write($socket, $msg, 4);

        //$this->SendDebug("Parameter für Heizfunktion", "".$socket.":".$msg."", 0);

        // Auswahl senden
        switch ($kuehlungValue)
        {
            case 0:
                $msg = pack('N*', 0); // Off
                break;
            case 1:
                $msg = pack('N*', 1); // Auto
                break;
            default:
                // Fallback auf einen Standardwert, falls der Wert außerhalb des erwarteten Bereichs liegt
                $msg = pack('N*', 0); // Auto
                break;
        }

        // Daten senden
        $send = socket_write($socket, $msg, 4);

        // Daten vom Socket empfangen und verarbeiten
        socket_recv($socket, $test, 4, MSG_WAITALL);  // Lesen, sollte 3002 zurückkommen
        $test = unpack('N*', $test);

        socket_recv($socket, $test, 4, MSG_WAITALL); // Lesen, sollte Status zurückkommen
        $test = unpack('N*', $test);

        // Socket schließen
        socket_close($socket);
    }
}
