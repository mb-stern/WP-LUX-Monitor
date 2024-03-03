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
                'set_223' => 'Woche von Set 1', 'set_224' => 'Woche bis Set 1',
                'set_225' => 'Woche von Set 2', 'set_226' => 'Woche bis Set 2',
                'set_227' => 'Woche von Set 3', 'set_228' => 'Woche bis Set 3'
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
                'set_223', 'set_224', 'set_225', 'set_226', 'set_227', 'set_228'
            ];
            
            foreach ($ids as $id) 
            {
                $this->UnregisterVariable($id);
            }
        }

        if ($timerWeekendVisible) 
        {
            $ids = [
                'set_229' => 'Mo-Fr von Set 1', 'set_230' => 'Mo-Fr bis Set 1', 'set_231' => 'Mo-Fr von Set 2', 'set_232' => 'Mo-Fr bis Set 2', 'set_233' => 'Mo-Fr von Set 3', 'set_234' => 'Mo-Fr bis Set 3',
                'set_235' => 'Sa+So von Set 1', 'set_236' => 'Sa+So bis Set 1', 'set_237' => 'Sa+So von Set 2', 'set_238' => 'Sa+So bis Set 2', 'set_239' => 'Sa+So von Set 3', 'set_240' => 'Sa+So bis Set 3'
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
                'set_229', 'set_230', 'set_231', 'set_232', 'set_233', 'set_234', 'set_235', 'set_236', 'set_237', 'set_238', 'set_239', 'set_240'
            ];
            
            foreach ($ids as $id) 
            {
                $this->UnregisterVariable($id);
            }
        }

        if ($timerDayVisible) 
        {
            $ids = [
                'set_241' => 'Sonntag von Set 1', 'set_242' => 'Sonntag bis Set 1', 'set_243' => 'Sonntag von Set 2', 'set_244' => 'Sonntag bis Set 2', 'set_245' => 'Sonntag von Set 3', 'set_246' => 'Sonntag bis Set 3',
                'set_247' => 'Montag von Set 1', 'set_248' => 'Montag bis Set 1', 'set_249' => 'Montag von Set 2', 'set_250' => 'Montag bis Set 2', 'set_251' => 'Montag von Set 3', 'set_252' => 'Montag bis Set 3',
                'set_253' => 'Dienstag von Set 1', 'set_254' => 'Dienstag bis Set 1', 'set_255' => 'Dienstag von Set 2', 'set_256' => 'Dienstag bis Set 2', 'set_257' => 'Dienstag von Set 3', 'set_258' => 'Dienstag bis Set 3',
                'set_259' => 'Mittwoch von Set 1', 'set_260' => 'Mittwoch bis Set 1', 'set_261' => 'Mittwoch von Set 2', 'set_262' => 'Mittwoch bis Set 2', 'set_263' => 'Mittwoch von Set 3', 'set_264' => 'Mittwoch bis Set 3',
                'set_265' => 'Donnerstag von Set 1', 'set_266' => 'Donnerstag bis Set 1', 'set_267' => 'Donnerstag von Set 2', 'set_268' => 'Donnerstag bis Set 2', 'set_269' => 'Donnerstag von Set 3', 'set_270' => 'Donnerstag bis Set 3',
                'set_271' => 'Freitag von Set 1', 'set_272' => 'Freitag bis Set 1', 'set_273' => 'Freitag von Set 2', 'set_274' => 'Freitag bis Set 2', 'set_275' => 'Freitag von Set 3', 'set_276' => 'Freitag bis Set 3',
                'set_277' => 'Samstag von Set 1', 'set_278' => 'Samstag bis Set 1', 'set_279' => 'Samstag von Set 2', 'set_280' => 'Samstag bis Set 2', 'set_281' => 'Samstag von Set 3', 'set_282' => 'Samstag bis Set 3'
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
                'set_241', 'set_242', 'set_243', 'set_244', 'set_245', 'set_246', 'set_247', 'set_248', 'set_249', 'set_250', 'set_251', 'set_252', 'set_253', 'set_254', 'set_255', 'set_256', 'set_257', 'set_258', 'set_259', 'set_260', 'set_261', 'set_262','set_263', 'set_264',
                'set_265', 'set_266', 'set_267', 'set_268', 'set_269', 'set_270', 'set_271', 'set_272', 'set_273', 'set_274', 'set_275', 'set_276', 'set_277', 'set_278', 'set_279', 'set_280', 'set_281', 'set_282'
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
            'set_223' => 'set_223', 'set_224' => 'set_224', 'set_225' => 'set_225', 'set_226' => 'set_226', 'set_227' => 'set_227', 'set_228' => 'set_228', 'set_229' => 'set_229', 'set_230' => 'set_230', 'set_231' => 'set_231', 'set_232' => 'set_232', 'set_233' => 'set_233',
            'set_234' => 'set_234', 'set_235' => 'set_235', 'set_236' => 'set_236', 'set_237' => 'set_237', 'set_238' => 'set_238', 'set_239' => 'set_239', 'set_240' => 'set_240', 'set_241' => 'set_241', 'set_242' => 'set_242', 'set_243' => 'set_243', 'set_244' => 'set_244',
            'set_245' => 'set_245', 'set_246' => 'set_246', 'set_247' => 'set_247', 'set_248' => 'set_248', 'set_249' => 'set_249', 'set_250' => 'set_250', 'set_251' => 'set_251', 'set_252' => 'set_252', 'set_253' => 'set_253', 'set_254' => 'set_254', 'set_255' => 'set_255',
            'set_256' => 'set_256', 'set_257' => 'set_257', 'set_258' => 'set_258', 'set_259' => 'set_259', 'set_260' => 'set_260', 'set_261' => 'set_261', 'set_262' => 'set_262', 'set_263' => 'set_263', 'set_264' => 'set_264', 'set_265' => 'set_265', 'set_266' => 'set_266',
            'set_267' => 'set_267', 'set_268' => 'set_268', 'set_269' => 'set_269', 'set_270' => 'set_270', 'set_271' => 'set_271', 'set_272' => 'set_272', 'set_273' => 'set_273', 'set_274' => 'set_274', 'set_275' => 'set_275', 'set_276' => 'set_276', 'set_277' => 'set_277',
            'set_278' => 'set_278', 'set_279' => 'set_279', 'set_280' => 'set_280', 'set_281' => 'set_281', 'set_282' => 'set_282'
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
            $this->DeleteVariableIfExists($java_dataset[$i]);
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
            case 'set_223':
                $parameter = 223;
                if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                break;
            case 'set_224':
                $parameter = 224;
                if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                break;
            case 'set_225':
                $parameter = 225;
                if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                break;
            case 'set_226':
                $parameter = 226;
                if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                break;
            case 'set_227':
                $parameter = 227;
                if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                break;
            case 'set_228':
                $parameter = 228;
                if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                break;
            case 'set_229':
                $parameter = 229;
                if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                break;
            case 'set_230':
                $parameter = 230;
                if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                break;
            case 'set_231':
                $parameter = 231;
                if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                break;
            case 'set_232':
                $parameter = 232;
                if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                break;
            case 'set_233':
                $parameter = 233;
                if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                break;
            case 'set_234':
                $parameter = 234;
                if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                break;
            case 'set_235':
                $parameter = 235;
                if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                break;
            case 'set_236':
                $parameter = 236;
                if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                break;
            case 'set_237':
                $parameter = 237;
                if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                break;
            case 'set_238':
                $parameter = 238;
                if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                break;
            case 'set_239':
                $parameter = 239;
                if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                break;
            case 'set_240':
                $parameter = 240;
                if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                break;
            case 'set_241':
                $parameter = 241;
                if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                break;
            case 'set_242':
                $parameter = 242;
                if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                break;
            case 'set_243':
                $parameter = 243;
                if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                break;
            case 'set_244':
                $parameter = 244;
                if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                break;
            case 'set_245':
                $parameter = 245;
                if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                break;
            case 'set_246':
                $parameter = 246;
                if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                break;
            case 'set_247':
                $parameter = 247;
                if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                break;
            case 'set_248':
                $parameter = 248;
                if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                break;
            case 'set_249':
                $parameter = 249;
                if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                break;
            case 'set_250':
                $parameter = 250;
                if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                break;
                case 'set_251':
                    $parameter = 251;
                    if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                    break;
                case 'set_252':
                    $parameter = 252;
                    if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                    break;
                case 'set_253':
                    $parameter = 253;
                    if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                    break;
                case 'set_254':
                    $parameter = 254;
                    if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                    break;
                case 'set_255':
                    $parameter = 255;
                    if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                    break;
                case 'set_256':
                    $parameter = 256;
                    if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                    break;
                case 'set_257':
                    $parameter = 257;
                    if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                    break;
                case 'set_258':
                    $parameter = 258;
                    if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                    break;
                case 'set_259':
                    $parameter = 259;
                    if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                    break;
                case 'set_260':
                    $parameter = 260;
                    if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                    break;
                case 'set_261':
                    $parameter = 261;
                    if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                    break;
                case 'set_262':
                    $parameter = 262;
                    if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                    break;
                case 'set_263':
                    $parameter = 263;
                    if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                    break;
                case 'set_264':
                    $parameter = 264;
                    if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                    break;
                case 'set_265':
                    $parameter = 265;
                    if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                    break;
                case 'set_266':
                    $parameter = 266;
                    if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                    break;
                case 'set_267':
                    $parameter = 267;
                    if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                    break;
                case 'set_268':
                    $parameter = 268;
                    if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                    break;
                case 'set_269':
                    $parameter = 269;
                    if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                    break;
                case 'set_270':
                    $parameter = 270;
                    if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                    break;
                case 'set_271':
                    $parameter = 271;
                    if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                    break;
                case 'set_272':
                    $parameter = 272;
                    if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                    break;
                case 'set_273':
                    $parameter = 273;
                    if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                    break;
                case 'set_274':
                    $parameter = 274;
                    if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                    break;
                case 'set_275':
                    $parameter = 275;
                    if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                    break;
                case 'set_276':
                    $parameter = 276;
                    if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                    break;
                case 'set_277':
                    $parameter = 277;
                    if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                    break;
                case 'set_278':
                    $parameter = 278;
                    if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                    break;
                case 'set_279':
                    $parameter = 279;
                    if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                    break;
                case 'set_280':
                    $parameter = 280;
                    if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                    break;
                case 'set_281':
                    $parameter = 281;
                    if ($value >= -3600 && $value <= 82800) $value += 3600; // Unix-Zeit korrigieren
                    break;
                case 'set_282':
                    $parameter = 282;
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
        switch ($mode) 
        {
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
                    case 'set_223':
                        $this->SetValue('set_223', $datenRaw[223] - 3600);
                        break;
                    case 'set_224':
                        $this->SetValue('set_224', $datenRaw[224] - 3600);
                        break;
                    case 'set_225':
                        $this->SetValue('set_225', $datenRaw[225] - 3600);
                        break;
                    case 'set_226':
                        $this->SetValue('set_226', $datenRaw[226] - 3600);
                        break;
                    case 'set_227':
                        $this->SetValue('set_227', $datenRaw[227] - 3600);
                        break;
                    case 'set_228':
                        $this->SetValue('set_228', $datenRaw[228] - 3600);
                        break;
                    case 'set_229':
                        $this->SetValue('set_229', $datenRaw[229] - 3600);
                        break;
                    case 'set_230':
                        $this->SetValue('set_230', $datenRaw[230] - 3600);
                        break;
                    case 'set_231':
                        $this->SetValue('set_231', $datenRaw[231] - 3600);
                        break;
                    case 'set_232':
                        $this->SetValue('set_232', $datenRaw[232] - 3600);
                        break;
                    case 'set_233':
                        $this->SetValue('set_233', $datenRaw[233] - 3600);
                        break;
                    case 'set_234':
                        $this->SetValue('set_234', $datenRaw[234] - 3600);
                        break;
                    case 'set_235':
                        $this->SetValue('set_235', $datenRaw[235] - 3600);
                        break;
                    case 'set_236':
                        $this->SetValue('set_236', $datenRaw[236] - 3600);
                        break;
                    case 'set_237':
                        $this->SetValue('set_237', $datenRaw[237] - 3600);
                        break;
                    case 'set_238':
                        $this->SetValue('set_238', $datenRaw[238] - 3600);
                        break;
                    case 'set_239':
                        $this->SetValue('set_239', $datenRaw[239] - 3600);
                        break;
                    case 'set_240':
                        $this->SetValue('set_240', $datenRaw[240] - 3600);
                        break;
                    case 'set_241':
                        $this->SetValue('set_241', $datenRaw[241] - 3600);
                        break;
                    case 'set_242':
                        $this->SetValue('set_242', $datenRaw[242] - 3600);
                        break;
                    case 'set_243':
                        $this->SetValue('set_243', $datenRaw[243] - 3600);
                        break;
                    case 'set_244':
                        $this->SetValue('set_244', $datenRaw[244] - 3600);
                        break;
                    case 'set_245':
                        $this->SetValue('set_245', $datenRaw[245] - 3600);
                        break;
                    case 'set_246':
                        $this->SetValue('set_246', $datenRaw[246] - 3600);
                        break;
                    case 'set_247':
                        $this->SetValue('set_247', $datenRaw[247] - 3600);
                        break;
                    case 'set_248':
                        $this->SetValue('set_248', $datenRaw[248] - 3600);
                        break;
                    case 'set_249':
                        $this->SetValue('set_249', $datenRaw[249] - 3600);
                        break;
                    case 'set_250':
                        $this->SetValue('set_250', $datenRaw[250] - 3600);
                        break;
                    case 'set_251':
                        $this->SetValue('set_251', $datenRaw[251] - 3600);
                        break;
                    case 'set_252':
                        $this->SetValue('set_252', $datenRaw[252] - 3600);
                        break;
                    case 'set_253':
                        $this->SetValue('set_253', $datenRaw[253] - 3600);
                        break;
                    case 'set_254':
                        $this->SetValue('set_254', $datenRaw[254] - 3600);
                        break;
                    case 'set_255':
                        $this->SetValue('set_255', $datenRaw[255] - 3600);
                        break;
                    case 'set_256':
                        $this->SetValue('set_256', $datenRaw[256] - 3600);
                        break;
                    case 'set_257':
                        $this->SetValue('set_257', $datenRaw[257] - 3600);
                        break;
                    case 'set_258':
                        $this->SetValue('set_258', $datenRaw[258] - 3600);
                        break;
                    case 'set_259':
                        $this->SetValue('set_259', $datenRaw[259] - 3600);
                        break;
                    case 'set_260':
                        $this->SetValue('set_260', $datenRaw[260] - 3600);
                        break;
                    case 'set_261':
                        $this->SetValue('set_261', $datenRaw[261] - 3600);
                        break;
                    case 'set_262':
                        $this->SetValue('set_262', $datenRaw[262] - 3600);
                        break;
                    case 'set_263':
                        $this->SetValue('set_263', $datenRaw[263] - 3600);
                        break;
                    case 'set_264':
                        $this->SetValue('set_264', $datenRaw[264] - 3600);
                        break;
                    case 'set_265':
                        $this->SetValue('set_265', $datenRaw[265] - 3600);
                        break;
                    case 'set_266':
                        $this->SetValue('set_266', $datenRaw[266] - 3600);
                        break;
                    case 'set_267':
                        $this->SetValue('set_267', $datenRaw[267] - 3600);
                        break;
                    case 'set_268':
                        $this->SetValue('set_268', $datenRaw[268] - 3600);
                        break;
                    case 'set_269':
                        $this->SetValue('set_269', $datenRaw[269] - 3600);
                        break;
                    case 'set_270':
                        $this->SetValue('set_270', $datenRaw[270] - 3600);
                        break;
                    case 'set_271':
                        $this->SetValue('set_271', $datenRaw[271] - 3600);
                        break;
                    case 'set_272':
                        $this->SetValue('set_272', $datenRaw[272] - 3600);
                        break;
                    case 'set_273':
                        $this->SetValue('set_273', $datenRaw[273] - 3600);
                        break;
                    case 'set_274':
                        $this->SetValue('set_274', $datenRaw[274] - 3600);
                        break;
                    case 'set_275':
                        $this->SetValue('set_275', $datenRaw[275] - 3600);
                        break;
                    case 'set_276':
                        $this->SetValue('set_276', $datenRaw[276] - 3600);
                        break;
                    case 'set_277':
                        $this->SetValue('set_277', $datenRaw[277] - 3600);
                            break;
                    case 'set_278':
                        $this->SetValue('set_278', $datenRaw[278] - 3600);
                        break;
                    case 'set_279':
                        $this->SetValue('set_279', $datenRaw[279] - 3600);
                        break;
                    case 'set_280':
                        $this->SetValue('set_280', $datenRaw[280] - 3600);
                        break;
                    case 'set_281':
                        $this->SetValue('set_281', $datenRaw[281] - 3600);
                        break;
                    case 'set_282':
                        $this->SetValue('set_282', $datenRaw[282] - 3600);
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