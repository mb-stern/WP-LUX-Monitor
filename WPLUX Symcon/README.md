# WPLUX Symcon
Dieses Modul ermöglicht, Daten der Luxtronic verschiedener Wärmepumpen-Hersteller abzufragen.
Dazu muss sichergestellt werden, dass der Port 8888 (ältere Lux) oder 8889 (neuere Lux) nicht durch die Firewall blockiert ist.
Dieses Modul funktioniert über Java-Abfrage, ab einem gewissen FW-Stand der LUX findet die Abfrage über Websocket statt. Java sollte aber weiterhin funktionieren.
Die Bedeutung und ID's der Variablen sind hier zu finden: https://loxwiki.atlassian.net/wiki/spaces/LOX/pages/1533935933/Java+Webinterface

![image](https://github.com/mb-stern/WPLUX-Symcon/assets/95777848/ff2b9244-a2b8-4d65-9903-bc2464c1c98d)


### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)
8. [Versionen](#8-versionen)

### 1. Funktionsumfang

* Abfrage der Istwerte aus der Luxtronic, welche in verschiedenen Wärmepumpen als Steuerung verbaut ist.
* Es werden automatisch die gewünschten Variablen angelegt und die benötigten Profile erstellt.
* Es werden jedoch nicht restlos alle Werte in Variablen aufgeschlüsselt, bei Bedarf ist daher der Name der Varaible/Wert manuell einzutragen.
* Ebenfalls werden je nach Wärmepumpen-Typ nicht alle Werte geliefert. Offensichtlich werden mit einer Software alle Wärmepumentypen abgedeckt.
* Es können nun Steuervariablen für Heizung, Warmwasser und Kühlung im Konfigurationsformuler aktiviert werden, je nach Funktionsumfang der Wärmepumpe. Die aktuellen Zustände werden von der Lux geholt. Mit jeder Änderung im Konfigurationsformuler werden diese Werte erneut von der Lux synchronisiert.

### 2. Voraussetzungen

- IP-Symcon ab Version 7.0

### 3. Software-Installation

* Über den Module Store kann das Modul noch nicht installiert werden.
* Alternativ über das Module Control folgende URL hinzufügen: https://github.com/mb-stern/WPLUX-Symcon

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'WPLUX Symcon'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name     | Beschreibung
-------- | ------------------
IP-Adresse      |	IP-Adresse des Rechners auf dem der Libre Hardware Monitor läuft
Port            |   Port der Luxtronic/Wärmepumpe (8888 oder 8889). Der Port muss in der Firewall geöffnet sein
Intervall       |   Intervall für das Update der Werte
Überwachte ID's  |  Hier die gewünschten ID's der Werte. Diese Wert sind hier ersichtlich https://loxwiki.atlassian.net/wiki/spaces/LOX/pages/1533935933/Java+Webinterface

![image](https://github.com/mb-stern/WPLUX-Symcon/assets/95777848/09640084-332b-41dc-9cba-0d13e3f7c273)


![image](https://github.com/mb-stern/WPLUX-Symcon/assets/95777848/ebd3b43d-d9aa-4e85-ad48-8721822e8764)


### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

Es werden Variablen/Typen je nach Wahl der Werte erstellt. Pro ID wird eine Variable erstellt.

#### Profile

Name   | Typ
------ | -------
WPLUX.Sec     |  Integer  
WPLUX.Imp     |  Integer   
WPLUX.Typ     |  Integer   
WPLUX.Biv     |  Integer   
WPLUX.BZ      |  Integer 
WPLUX.Comf    |  Bool 
WPLUX.Men1    |  Integer
WPLUX.Men2    |  Integer
WPLUX.Men3    |  Integer
WPLUX.Akt     |  Bool
WPLUX.Pres    |  Float
WPLUX.Fan    |  Integer
WPLUX.lh	|  Integer
WPLUX.Wwhe	|  Integer
WPLUX.Kue	|  Integer


### 6. WebFront

Die Funktionalität, die das Modul im WebFront bietet.

### 7. PHP-Befehlsreferenz

`boolean WPLUX_BeispielFunktion(integer $InstanzID);`
Erklärung der Funktion.

Beispiel:
`WPLUX_Update(12345);`

### 8. Versionen

Version 1.3 (09.02.2024)

- Minustest für Aussentemperatur hinzugefügt, so dass plausible Minuswerte ausgegeben werden
- Erhöhung der Java-ID's auf 267 sollte Fehlermeldungen gewisser Luxtronic vermeiden.
- Es können nun Steuervariablen für Heizung, Warmwasser und Kühlung aktiviert werden.

Version 1.2 (05.02.2024)

- Muster IP-Adresse wird nicht mehr standardmässig geladen bei Installation des Moduls. Dies führte bei der Installation zu Fehlermeldungen.

Version 1.1 (04.02.2024)

- Kleine Anpassungen an der Variabelzuweisung und Konvertierung
- Vergössertes Fenster Sortierung der ID's im Konfigurationsformular

Version 1.0 (04.02.2024)

- Initiale Version
