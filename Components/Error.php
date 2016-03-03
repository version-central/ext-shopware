<?php
namespace Shopware\Plugins\VersionCentralTracker\Components;

class Error
{
    public static function getErrorMessage($errorCode)
    {
        switch ($errorCode) {
            case 'application_type_changed':
                return "Dieses Token ist bereits von einer anderen Anwendung in Benutzung. Bitte benutzen Sie das korrekte Token für Ihre Anwendung oder erstellen Sie eine neue Anwendung in VersionCentral.";
            case 'api_credentials_invalid':
                return 'Verbindung nicht erfolgreich, bitte prüfen Sie Ihre API-Daten.';
            default:
                return "Ein Fehler ist bei der Übertragung an VersionCentral aufgetreten. Bitte setzen Sie sich mit uns in Verbindung für weitere Informationen und Unterstützung.";
        }
    }
}
