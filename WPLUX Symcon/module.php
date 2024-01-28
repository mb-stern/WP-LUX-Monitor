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

        $this->RegisterPropertyString('IPAddress', '192.168.178.59');
        $this->RegisterPropertyInteger('Port', 8889);
        $this->RegisterPropertyString('IDListe', '[]');
        $this->RegisterPropertyInteger('UpdateInterval', 0);

        // Timer für Aktualisierung registrieren
        $this->RegisterTimer('UpdateTimer', 0, 'WPLUX_Update(' . $this->InstanceID . ');');
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        // Timer für Aktualisierung aktualisieren
        $this->SetTimerInterval('UpdateTimer', $this->ReadPropertyInteger('UpdateInterval') * 1000);

        // Bei Änderungen am Konfigurationsformular oder bei der Initialisierung auslösen
        $this->Update();
    }

    public function Update()
    {
        // ...

        // Werte anzeigen
        for ($i = 0; $i < $JavaWerte; ++$i) {
            if (in_array($i, array_column($idListe, 'id'))) {
                $value = $this->convertValueBasedOnID($daten_raw[$i], $i);

                // Debug senden
                $this->SendDebug("Gewählte ID für Abfrage", "$i", 0);

                // Direkte Erstellung oder Aktualisierung der Variable mit Ident und Positionsnummer
                $ident = 'WP_' . $java_dataset[$i];
                $varid = $this->CreateOrUpdateVariable($ident, $value, $i);
            } else {
                // Variable löschen, da sie nicht mehr in der ID-Liste ist
                $this->DeleteVariableIfExists('WP_' . $java_dataset[$i]);
            }
        }
    }

    private function CreateOrUpdateVariable($ident, $value, $id)
    {
        $value = $this->convertValueBasedOnID($value, $id);

        // Debug-Ausgabe
        $this->SendDebug("Variabelwert aktualisiert", "$ident", 0);

        // Überprüfen, ob die Variable bereits existiert
        $existingVarID = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);

        if ($existingVarID === false) {
            // Variable existiert nicht, also erstellen
            $varid = IPS_CreateVariable($this->getVariableTypeBasedOnID($id));
            IPS_SetParent($varid, $this->InstanceID);
            IPS_SetIdent($varid, $ident);
            IPS_SetName($varid, $ident);
            SetValue($varid, $value);
            IPS_SetPosition($varid, $id);

            // Einstellungen der Variable zuweisen
            $this->AssignVariableSettings($varid, $id);
        } else {
            // Variable existiert, also aktualisieren
            SetValue($existingVarID, $value);
            $this->AssignVariableSettings($existingVarID, $id); // Aktualisiere auch die Einstellungen
            $varid = $existingVarID; // Setze $varid auf die existierende ID
        }

        return isset($varid) ? $varid : 0; // Rückgabe von $varid oder 0, wenn nicht gesetzt
    }

    private function AssignVariableSettings($varid, $id)
    {
        // Hier erfolgt die Zuordnung der Einstellungen basierend auf der 'id'
        switch ($id) {
            case 10:
                $variableType = 2; // Integer-Typ
                $profile = '~Temperature';
                break;
            case 29:
                $variableType = 0; // Boolean-Typ
                $profile = '~Switch';
                break;
            // Weitere Zuordnungen für andere 'id' hinzufügen
            default:
                $variableType = 2; // Standardmäßig Integer-Typ
                $profile = '';
                break;
        }

        // Hier erfolgt die Zuordnung des Variablenprofils basierend auf der 'id'
        IPS_SetVariableCustomProfile($varid, $profile);

        return $variableType;
    }

    private function convertValueBasedOnID($value, $id)
    {
        // Hier erfolgt die Konvertierung des Werts basierend auf der 'id'
        switch ($id) {
            case 10:
                return round($value * 0.1, 1); // Hier ggf. Anpassungen für Integer-Typ
            case 29:
                return boolval($value); // Hier ggf. Anpassungen für Boolean-Typ
            // Weitere Zuordnungen für andere 'id' hinzufügen
            default:
                return round($value * 0.1, 1); // Standardmäßig Konvertierung für Integer-Typ
        }
    }

    private function DeleteVariableIfExists($ident)
    {
        $variableID = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);
        if ($variableID !== false) {
            // Debug-Ausgabe
            $this->Log("Variable löschen: " . $ident);

            // Variable löschen
            IPS_DeleteVariable($variableID);
        }
    }

    private function getVariableTypeBasedOnID($id)
    {
        // Hier erfolgt die Zuordnung des Variablentyps basierend auf der 'id'
        switch ($id) {
            case 10:
                return 2; // Integer-Typ
            case 29:
                return 0; // Boolean-Typ
            // Weitere Zuordnungen für andere 'id' hinzufügen
            default:
                return 2; // Standardmäßig Integer-Typ
        }
    }
}


        
