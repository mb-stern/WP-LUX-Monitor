# Modul für Wärmepumpen mit Luxtronic für IP-Symcon

Folgende Module beinhaltet das WPLUX Symcon Repository:

- __Luxtronic__ ([Dokumentation](WPLUX%20Symcon))  
Dieses Modul ermöglicht, Daten der Luxtronic von verschiedener Wärmepumpen-Hersteller abzufragen.
Dazu muss sichergestellt werden, dass der Port 8888 (ältere Lux) oder 8889 (neuere Lux) nicht durch die Firewall blockiert ist.
Dieses Modul funktioniert nur mit Java-Abfrage, ab einem gewissen FW-Stand findet die Abfrage über Websocket statt.
Die Bedeutung den Typ der Varaibalen sind hier zu finden: https://loxwiki.atlassian.net/wiki/spaces/LOX/pages/1533935933/Java+Webinterface
