<?php

use App\Enums\PartnerUserRoles;
use App\Enums\TrolleymakerUserRoles;
use App\Enums\UserRoles;
use App\Http\Controllers\LocalizationController;
use App\Http\Middleware\AuthenticateWithApiKey;
use App\Http\Middleware\AuthenticateWithApiOptional;
use App\Http\Middleware\CheckIfApiKeyForRegion;
use App\Http\Middleware\RedirectIfApiRouteMissing;
use App\Mail\AddedCardIsEmployeeCard;
use App\Mail\CheckEcTerminalCustomerMail;
use App\Mail\CheckEcTerminalMail;
use App\Mail\ContactFormCustomerMail;
use App\Mail\ContactFormMail;
use App\Mail\CorrectionBookingCustomerMail;
use App\Mail\CorrectionBookingMail;
use App\Mail\ErrorNotificationMail;
use App\Mail\InterestContactFormCustomerMail;
use App\Mail\InterestContactFormMail;
use App\Mail\PersonalDataCompleteEmployerMail;
use App\Mail\PersonalDataCompletePartnerMail;
use App\Mail\RegistrationCustomerMail;
use App\Mail\RegistrationEmployerCustomerMail;
use App\Mail\RegistrationEmployerMail;
use App\Mail\RegistrationInterestCustomerMail;
use App\Mail\RegistrationInterestMail;
use App\Mail\RegistrationNewBranchUserCustomerMail;
use App\Mail\RegistrationPartnerCustomerMail;
use App\Mail\RegistrationPartnerMail;
use App\Mail\ResetPasswordCustomerMail;
use App\Mail\ResetPasswordSuccessCustomerMail;
use App\Mail\SetBonusCustomerMail;
use App\Mail\SetBonusMail;
use App\Mail\TransferBalanceCustomerMail;
use App\Mail\TransferBalanceMail;
use App\Modules\Trolleymaker\Database\Models\Card;
use Carbon\Carbon;
use Cocur\Slugify\Slugify;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use laraveldes3\DES3;
use Modules\Trolleymaker\Database\Enums\GenesisWorld\Columns\KARTENVERWALTUNG;

ini_set('serialize_precision',-1);


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

//DEV: VFJPTExFWU1BS0VSREJfVEVTVC9BZG1pbmlzdHJhdG9yOkdXVE0yMDIyX1Rlc3Q=
//PROD: QWRtaW5pc3RyYXRvcjpHV1RNMjAyMg==

Route::group(
    [
        'prefix'     => 'api/v1',
        'controller' => LocalizationController::class,
    ],
    function () {
        Route::get('lang', 'get')->name('lang');
    });

Route::get('/partners/{partnerGguid}', function (Request $request, string $partnerGguid) {

    $company_data = getGwPersonalDataByGGUID($partnerGguid);
    if(isError($company_data)) {
        return returnErrorObject($company_data);
    }

    $response = new stdClass();
    $response->closedMonday = property_exists($company_data, 'TMPARTNERDATENVOLLSTAENDIG') ? $company_data->TMPARTNERHATGESCHLOSSENMO : true;
    $response->closedTuesday = property_exists($company_data, 'TMPARTNERHATGESCHLOSSENDI') ? $company_data->TMPARTNERHATGESCHLOSSENDI : true;
    $response->closedWednesday = property_exists($company_data, 'TMPARTNERHATGESCHLOSSENMI') ? $company_data->TMPARTNERHATGESCHLOSSENMI : true;
    $response->closedThursday = property_exists($company_data, 'TMPARTNERHATGESCHLOSSENDO') ? $company_data->TMPARTNERHATGESCHLOSSENDO : true;
    $response->closedFriday = property_exists($company_data, 'TMPARTNERHATGESCHLOSSENFR') ? $company_data->TMPARTNERHATGESCHLOSSENFR : true;
    $response->closedSaturday = property_exists($company_data, 'TMPARTNERHATGESCHLOSSENSA') ? $company_data->TMPARTNERHATGESCHLOSSENSA : true;
    $response->closedSunday = property_exists($company_data, 'TMPARTNERHATGESCHLOSSENSO') ? $company_data->TMPARTNERHATGESCHLOSSENSO : true;

    $openingHours = new stdClass();
    $openingHours->mon = [];
    $openingHours->tue = [];
    $openingHours->wed = [];
    $openingHours->thu = [];
    $openingHours->fri = [];
    $openingHours->sat = [];
    $openingHours->sun = [];
    $openingHours->mon = [];
    if(property_exists($company_data, 'TMOEFFZEITMONTAG1VON') && !empty($company_data->TMOEFFZEITMONTAG1VON) && property_exists($company_data, 'TMOEFFZEITMONTAG1BIS') && !empty($company_data->TMOEFFZEITMONTAG1BIS)) {
        $temp = new stdClass();
        $temp->start = property_exists($company_data, 'TMOEFFZEITMONTAG1VON') ? $company_data->TMOEFFZEITMONTAG1VON : '';
        $temp->end = property_exists($company_data, 'TMOEFFZEITMONTAG1BIS') ? $company_data->TMOEFFZEITMONTAG1BIS : '';
        array_push($openingHours->mon, $temp);
    }
    if(property_exists($company_data, 'TMOEFFZEITMONTAG2VON') && !empty($company_data->TMOEFFZEITMONTAG2VON) && property_exists($company_data, 'TMOEFFZEITMONTAG2BIS') && !empty($company_data->TMOEFFZEITMONTAG2BIS)) {
        $temp = new stdClass();
        $temp->start = property_exists($company_data, 'TMOEFFZEITMONTAG2VON') ? $company_data->TMOEFFZEITMONTAG2VON : '';
        $temp->end = property_exists($company_data, 'TMOEFFZEITMONTAG2BIS') ? $company_data->TMOEFFZEITMONTAG2BIS : '';
        array_push($openingHours->mon, $temp);
    }
    if(property_exists($company_data, 'TMOEFFZEITDIENSTAG1VON') && !empty($company_data->TMOEFFZEITDIENSTAG1VON) && property_exists($company_data, 'TMOEFFZEITDIENSTAG1BIS') && !empty($company_data->TMOEFFZEITDIENSTAG1BIS)) {
        $temp = new stdClass();
        $temp->start = property_exists($company_data, 'TMOEFFZEITDIENSTAG1VON') ? $company_data->TMOEFFZEITDIENSTAG1VON : '';
        $temp->end = property_exists($company_data, 'TMOEFFZEITDIENSTAG1BIS') ? $company_data->TMOEFFZEITDIENSTAG1BIS : '';
        array_push($openingHours->tue, $temp);
    }
    if(property_exists($company_data, 'TMOEFFZEITDIENSTAG2VON') && !empty($company_data->TMOEFFZEITDIENSTAG2VON) && property_exists($company_data, 'TMOEFFZEITDIENSTAG2BIS') && !empty($company_data->TMOEFFZEITDIENSTAG2BIS)) {
        $temp = new stdClass();
        $temp->start = property_exists($company_data, 'TMOEFFZEITDIENSTAG2VON') ? $company_data->TMOEFFZEITDIENSTAG2VON : '';
        $temp->end = property_exists($company_data, 'TMOEFFZEITDIENSTAG2BIS') ? $company_data->TMOEFFZEITDIENSTAG2BIS : '';
        array_push($openingHours->tue, $temp);
    }
    if(property_exists($company_data, 'TMOEFFZEITMITTWOCH1VON') && !empty($company_data->TMOEFFZEITMITTWOCH1VON) && property_exists($company_data, 'TMOEFFZEITMITTWOCH1BIS') && !empty($company_data->TMOEFFZEITMITTWOCH1BIS)) {
        $temp = new stdClass();
        $temp->start = property_exists($company_data, 'TMOEFFZEITMITTWOCH1VON') ? $company_data->TMOEFFZEITMITTWOCH1VON : '';
        $temp->end = property_exists($company_data, 'TMOEFFZEITMITTWOCH1BIS') ? $company_data->TMOEFFZEITMITTWOCH1BIS : '';
        array_push($openingHours->wed, $temp);
    }
    if(property_exists($company_data, 'TMOEFFZEITMITTWOCH2VON') && !empty($company_data->TMOEFFZEITMITTWOCH2VON) && property_exists($company_data, 'TMOEFFZEITMITTWOCH2BIS') && !empty($company_data->TMOEFFZEITMITTWOCH2BIS)) {
        $temp = new stdClass();
        $temp->start = property_exists($company_data, 'TMOEFFZEITMITTWOCH2VON') ? $company_data->TMOEFFZEITMITTWOCH2VON : '';
        $temp->end = property_exists($company_data, 'TMOEFFZEITMITTWOCH2BIS') ? $company_data->TMOEFFZEITMITTWOCH2BIS : '';
        array_push($openingHours->wed, $temp);
    }
    if(property_exists($company_data, 'TMOEFFZEITDONNERSTAG1VON') && !empty($company_data->TMOEFFZEITDONNERSTAG1VON) && property_exists($company_data, 'TMOEFFZEITDONNERSTAG1BIS') && !empty($company_data->TMOEFFZEITDONNERSTAG1BIS)) {
        $temp = new stdClass();
        $temp->start = property_exists($company_data, 'TMOEFFZEITDONNERSTAG1VON') ? $company_data->TMOEFFZEITDONNERSTAG1VON : '';
        $temp->end = property_exists($company_data, 'TMOEFFZEITDONNERSTAG1BIS') ? $company_data->TMOEFFZEITDONNERSTAG1BIS : '';
        array_push($openingHours->thu, $temp);
    }
    if(property_exists($company_data, 'TMOEFFZEITDONNERSTAG2VON') && !empty($company_data->TMOEFFZEITDONNERSTAG2VON) && property_exists($company_data, 'TMOEFFZEITDONNERSTAG2BIS') && !empty($company_data->TMOEFFZEITDONNERSTAG2BIS)) {
        $temp = new stdClass();
        $temp->start = property_exists($company_data, 'TMOEFFZEITDONNERSTAG2VON') ? $company_data->TMOEFFZEITDONNERSTAG2VON : '';
        $temp->end = property_exists($company_data, 'TMOEFFZEITDONNERSTAG2BIS') ? $company_data->TMOEFFZEITDONNERSTAG2BIS : '';
        array_push($openingHours->thu, $temp);
    }
    if(property_exists($company_data, 'TMOEFFZEITFREITAG1VON') && !empty($company_data->TMOEFFZEITFREITAG1VON) && property_exists($company_data, 'TMOEFFZEITFREITAG1BIS') && !empty($company_data->TMOEFFZEITFREITAG1BIS)) {
        $temp = new stdClass();
        $temp->start = property_exists($company_data, 'TMOEFFZEITFREITAG1VON') ? $company_data->TMOEFFZEITFREITAG1VON : '';
        $temp->end = property_exists($company_data, 'TMOEFFZEITFREITAG1BIS') ? $company_data->TMOEFFZEITFREITAG1BIS : '';
        array_push($openingHours->fri, $temp);
    }
    if(property_exists($company_data, 'TMOEFFZEITFREITAG2VON') && !empty($company_data->TMOEFFZEITFREITAG2VON) && property_exists($company_data, 'TMOEFFZEITFREITAG2BIS') && !empty($company_data->TMOEFFZEITFREITAG2BIS)) {
        $temp = new stdClass();
        $temp->start = property_exists($company_data, 'TMOEFFZEITFREITAG2VON') ? $company_data->TMOEFFZEITFREITAG2VON : '';
        $temp->end = property_exists($company_data, 'TMOEFFZEITFREITAG2BIS') ? $company_data->TMOEFFZEITFREITAG2BIS : '';
        array_push($openingHours->fri, $temp);
    }
    if(property_exists($company_data, 'TMOEFFZEITSAMSTAG1VON') && !empty($company_data->TMOEFFZEITSAMSTAG1VON) && property_exists($company_data, 'TMOEFFZEITSAMSTAG1BIS') && !empty($company_data->TMOEFFZEITSAMSTAG1BIS)) {
        $temp = new stdClass();
        $temp->start = property_exists($company_data, 'TMOEFFZEITSAMSTAG1VON') ? $company_data->TMOEFFZEITSAMSTAG1VON : '';
        $temp->end = property_exists($company_data, 'TMOEFFZEITSAMSTAG1BIS') ? $company_data->TMOEFFZEITSAMSTAG1BIS : '';
        array_push($openingHours->sat, $temp);
    }
    if(property_exists($company_data, 'TMOEFFZEITSAMSTAG2VON') && !empty($company_data->TMOEFFZEITSAMSTAG2VON) && property_exists($company_data, 'TMOEFFZEITSAMSTAG2BIS') && !empty($company_data->TMOEFFZEITSAMSTAG2BIS)) {
        $temp = new stdClass();
        $temp->start = property_exists($company_data, 'TMOEFFZEITSAMSTAG2VON') ? $company_data->TMOEFFZEITSAMSTAG2VON : '';
        $temp->end = property_exists($company_data, 'TMOEFFZEITSAMSTAG2BIS') ? $company_data->TMOEFFZEITSAMSTAG2BIS : '';
        array_push($openingHours->sat, $temp);
    }
    if(property_exists($company_data, 'TMOEFFZEITSONNTAG1VON') && !empty($company_data->TMOEFFZEITSONNTAG1VON) && property_exists($company_data, 'TMOEFFZEITSONNTAG1BIS') && !empty($company_data->TMOEFFZEITSONNTAG1BIS)) {
        $temp = new stdClass();
        $temp->start = property_exists($company_data, 'TMOEFFZEITSONNTAG1VON') ? $company_data->TMOEFFZEITSONNTAG1VON : '';
        $temp->end = property_exists($company_data, 'TMOEFFZEITSONNTAG1BIS') ? $company_data->TMOEFFZEITSONNTAG1BIS : '';
        array_push($openingHours->sun, $temp);
    }
    if(property_exists($company_data, 'TMOEFFZEITSONNTAG2VON') && !empty($company_data->TMOEFFZEITSONNTAG2VON) && property_exists($company_data, 'TMOEFFZEITSONNTAG2BIS') && !empty($company_data->TMOEFFZEITSONNTAG2BIS)) {
        $temp = new stdClass();
        $temp->start = property_exists($company_data, 'TMOEFFZEITSONNTAG2VON') ? $company_data->TMOEFFZEITSONNTAG2VON : '';
        $temp->end = property_exists($company_data, 'TMOEFFZEITSONNTAG2BIS') ? $company_data->TMOEFFZEITSONNTAG2BIS : '';
        array_push($openingHours->sun, $temp);
    }

    $response->openingHours = $openingHours;
    $response->companyOpenHoursAdditionalInfo = property_exists($company_data, 'TMINFOOEFFNUNGSZEIT') ? $company_data->TMINFOOEFFNUNGSZEIT : '';
    $response->companyOpenHoursOnlyByArrangement = property_exists($company_data, 'TMTERMINVEREINBARUNG') ? $company_data->TMTERMINVEREINBARUNG : false;

    $response->companyName = property_exists($company_data, 'COMPNAME2') ? $company_data->COMPNAME2 : "";
    $response->category = property_exists($company_data, 'CATEGORY') ? $company_data->CATEGORY : "";
    $response->city = property_exists($company_data, 'TOWN2') ? $company_data->TOWN2 : "";
    $response->street = property_exists($company_data, 'STREET2') ? $company_data->STREET2 : "";
    $response->zip = property_exists($company_data, 'ZIP2') ? $company_data->ZIP2 : "";
    $response->country = property_exists($company_data, 'COUNTRY2') ? $company_data->COUNTRY2 : "";
    $response->phone = property_exists($company_data, 'TMPHONEVEROEFFENTLICHUNG') ? $company_data->TMPHONEVEROEFFENTLICHUNG : "";
    $response->email = property_exists($company_data, 'TMMAILVEROEFFENTLICHUNG') ? $company_data->TMMAILVEROEFFENTLICHUNG : "";
    if(property_exists($company_data, 'WWWFIELDSTR1')) {
        if(str_starts_with(strtolower($company_data->WWWFIELDSTR1), 'http')) {
            $response->website = $company_data->WWWFIELDSTR1;
        } else {
            $response->website = 'https://' . $company_data->WWWFIELDSTR1;
        }
    } else {
        $response->website = "";
    }
    $response->latitude = property_exists($company_data, 'GWLATITUDE') ? $company_data->GWLATITUDE : 0;
    $response->longitude = property_exists($company_data, 'GWLONGITUDE') ? $company_data->GWLONGITUDE : 0;


    $response->anyBonusActive = property_exists($company_data, 'TMBONUSAKTIVIERT') ? $company_data->TMBONUSAKTIVIERT : false;
    if($response->anyBonusActive == true) {
        $response->permanentBonusActive = property_exists($company_data, 'TMDAUERBONUS') ? $company_data->TMDAUERBONUS : false;
        if($response->permanentBonusActive == true) {
            $response->permanentBonusType = property_exists($company_data, 'TMDAUERBONUSART') ? $company_data->TMDAUERBONUSART : "";
            $response->permanentBonusPercent = property_exists($company_data, 'TMDBONUSINPROZENT') ? number_format($company_data->TMDBONUSINPROZENT, 2, ',', '.') : NULL;
            $response->permanentBonusPercentMinSale = property_exists($company_data, 'TMDBONUSPROZENTMINDESTUMSATZ') ? number_format($company_data->TMDBONUSPROZENTMINDESTUMSATZ, 2, ',', '.') : NULL;
            $response->permanentBonusAmount = property_exists($company_data, 'TMDBONUSBETRAG') ? number_format($company_data->TMDBONUSBETRAG, 2, ',', '.') : NULL;
            $response->permanentBonusAmountMinSale = property_exists($company_data, 'TMDBONUSBETRAGMINDESTUMSATZ') ? number_format($company_data->TMDBONUSBETRAGMINDESTUMSATZ, 2, ',', '.') : NULL;
            $response->permanentBonusForEntirePurchase = property_exists($company_data, 'TMDBONUSEINKAUFGESAMT') ? $company_data->TMDBONUSEINKAUFGESAMT : 'Nein';
            $response->permanentBonusOnlyForSpecificTimes = (property_exists($company_data, 'TMDBONUSZEITSTEUERUNG') and $company_data->TMDBONUSZEITSTEUERUNG != 'Nein') ? $company_data->TMDBONUSZEITSTEUERUNG : 'Nein';
            $response->permanentBonusInfoText = '';
            if($response->permanentBonusOnlyForSpecificTimes != NULL && $response->permanentBonusOnlyForSpecificTimes != 'Nein') {
                $response->permanentBonusInfoText .= 'In der Zeit: ' . $response->permanentBonusOnlyForSpecificTimes . ', ';
            }
            if($response->permanentBonusForEntirePurchase == 'Ja' || $response->permanentBonusForEntirePurchase === true) {
                if($response->permanentBonusType == 'Prozentualer Bonus vom Einkaufswert') {
                    $response->permanentBonusInfoText .= $response->permanentBonusPercent . '% auf ihren Einkauf';
                } else if($response->permanentBonusType == 'Prozentualer Bonus vom Einkaufswert in Kombination mit einem Mindestumsatz') {
                    $response->permanentBonusInfoText .= $response->permanentBonusPercent . '% auf ihren Einkauf ab einem Mindestumsatz von ' . $response->permanentBonusPercentMinSale . '€';
                } else if($response->permanentBonusType == 'Festbetrag') {
                    $response->permanentBonusInfoText .= $response->permanentBonusAmount . '€ auf ihren Einkauf';
                } else if($response->permanentBonusType == 'Festbetrag in Kombination mit einem Mindestumsatz') {
                    $response->permanentBonusInfoText .= $response->permanentBonusAmount . '€ auf ihren Einkauf ab einem Mindestumsatz von ' . $response->permanentBonusAmountMinSale . '€';
                }
            } else {
                if($response->permanentBonusType == 'Prozentualer Bonus vom Einkaufswert') {
                    $response->permanentBonusInfoText .= $response->permanentBonusPercent . '% außer: ' . $response->permanentBonusForEntirePurchase;
                } else if($response->permanentBonusType == 'Prozentualer Bonus vom Einkaufswert in Kombination mit einem Mindestumsatz') {
                    $response->permanentBonusInfoText .= $response->permanentBonusPercent . '% ab einem Mindestumsatz von ' . $response->permanentBonusPercentMinSale . '€, außer: ' . $response->permanentBonusForEntirePurchase;
                } else if($response->permanentBonusType == 'Festbetrag') {
                    $response->permanentBonusInfoText .= $response->permanentBonusAmount . '€ außer: ' . $response->permanentBonusForEntirePurchase;
                } else if($response->permanentBonusType == 'Festbetrag in Kombination mit einem Mindestumsatz') {
                    $response->permanentBonusInfoText .= $response->permanentBonusAmount . '€ ab einem Mindestumsatz von ' . $response->permanentBonusAmountMinSale . '€, außer: ' . $response->permanentBonusForEntirePurchase;
                }
            }
        }
        $response->promotionalBonusActive = property_exists($company_data, 'TMAKTIONSBONUS') ? $company_data->TMAKTIONSBONUS : false;
        if($response->promotionalBonusActive == true) {
            $response->promotionalBonusType = property_exists($company_data, 'TMAKTIONSBONUSART') ? $company_data->TMAKTIONSBONUSART : "";
            $response->promotionalBonusPercent = property_exists($company_data, 'TMABONUSINPROZENT') ? number_format($company_data->TMABONUSINPROZENT, 2, ',', '.') : NULL;
            $response->promotionalBonusPercentMinSale = property_exists($company_data, 'TMABONUSPROZENTMINDESTUMSATZ') ? number_format($company_data->TMABONUSPROZENTMINDESTUMSATZ, 2, ',', '.') : NULL;
            $response->promotionalBonusAmount = property_exists($company_data, 'TMABONUSBETRAG') ? number_format($company_data->TMABONUSBETRAG, 2, ',', '.') : NULL;
            $response->promotionalBonusAmountMinSale = property_exists($company_data, 'TMABONUSBETRAGMINDESTUMSATZ') ? number_format($company_data->TMABONUSBETRAGMINDESTUMSATZ, 2, ',', '.') : NULL;
            $response->promotionalBonusForEntirePurchase = property_exists($company_data, 'TMABONUSEINKAUFGESAMT') ? $company_data->TMABONUSEINKAUFGESAMT : 'Nein';
            $response->promotionalBonusOnlyForSpecificTimes = (property_exists($company_data, 'TMABONUSZEITSTEUERUNG') and $company_data->TMABONUSZEITSTEUERUNG != 'Nein') ? $company_data->TMABONUSZEITSTEUERUNG : 'Nein';
            $response->promotionalBonusInfoText = '';
            if($response->promotionalBonusOnlyForSpecificTimes != NULL && $response->promotionalBonusOnlyForSpecificTimes != 'Nein') {
                $response->promotionalBonusInfoText .= 'In der Zeit: ' . $response->promotionalBonusOnlyForSpecificTimes . ', ';
            }
            if($response->promotionalBonusForEntirePurchase == 'Ja' || $response->promotionalBonusForEntirePurchase === true) {
                if($response->promotionalBonusType == 'Prozentualer Bonus vom Einkaufswert') {
                    $response->promotionalBonusInfoText .= $response->promotionalBonusPercent . '% auf ihren Einkauf';
                } else if($response->promotionalBonusType == 'Prozentualer Bonus vom Einkaufswert in Kombination mit einem Mindestumsatz') {
                    $response->promotionalBonusInfoText .= $response->promotionalBonusPercent . '% auf ihren Einkauf ab einem Mindestumsatz von ' . $response->promotionalBonusPercentMinSale . '€';
                } else if($response->promotionalBonusType == 'Festbetrag') {
                    $response->promotionalBonusInfoText .= $response->promotionalBonusAmount . '€ auf ihren Einkauf';
                } else if($response->promotionalBonusType == 'Festbetrag in Kombination mit einem Mindestumsatz') {
                    $response->promotionalBonusInfoText .= $response->promotionalBonusAmount . '€ auf ihren Einkauf ab einem Mindestumsatz von ' . $response->promotionalBonusAmountMinSale . '€';
                }
            } else {
                if($response->promotionalBonusType == 'Prozentualer Bonus vom Einkaufswert') {
                    $response->promotionalBonusInfoText .= $response->promotionalBonusPercent . '% außer: ' . $response->promotionalBonusForEntirePurchase;
                } else if($response->promotionalBonusType == 'Prozentualer Bonus vom Einkaufswert in Kombination mit einem Mindestumsatz') {
                    $response->promotionalBonusInfoText .= $response->promotionalBonusPercent . '% ab einem Mindestumsatz von ' . $response->promotionalBonusPercentMinSale . '€, außer: ' . $response->promotionalBonusForEntirePurchase;
                } else if($response->promotionalBonusType == 'Festbetrag') {
                    $response->promotionalBonusInfoText .= $response->promotionalBonusAmount . '€ außer: ' . $response->promotionalBonusForEntirePurchase;
                } else if($response->promotionalBonusType == 'Festbetrag in Kombination mit einem Mindestumsatz') {
                    $response->promotionalBonusInfoText .= $response->promotionalBonusAmount . '€ ab einem Mindestumsatz von ' . $response->promotionalBonusAmountMinSale . '€, außer: ' . $response->promotionalBonusForEntirePurchase;
                }
            }
        }
    }

    $response->logoUrl = 'https://backend.mycity.cards/api/v1/partners/' . $company_data->GGUID . '/logo.png';

    $featured_images = getDocumentsForCompany($company_data->GGUID, ['titelbild'], ['jpg', 'jpeg', 'png'], 'empfangen');

    if(!is_array($featured_images) && property_exists($featured_images, 'errorMessage') && !empty($featured_images->errorMessage)) {
        return response()->json( $featured_images, 500 );
    } else {
        if(is_array($featured_images)) {
            if(count($featured_images) > 0) {
                $response->featuredImageUrl = 'https://backend.mycity.cards/api/v1/partners/' . $featured_images[0]->gguid . '/titelbild.jpg';
            } else {
                $response->featuredImageUrl = getCardNameImageUrl($request->input('card_name'));
            }
        } else {
            $response->featuredImageUrl = getCardNameImageUrl($request->input('card_name'));
        }
    }

    return response()->json( $response, 200 );
})->middleware(['AuthenticateWithSession']);


Route::get('/partners', function (Request $request) {

    $result = handleGetPartners($request);

    if(isError($result)){
        return returnErrorObject($result);
    }

    return response()->json($result, 200);

})->middleware(['AuthenticateWithSession']);


function handleGetPartners($request) {

    $excludeTestRecords = true;
    if(App::environment(['development'])) {
        $excludeTestRecords = false;
    }

    $showOnlyWhiteLabelVisiblePartners = true;
    if($request->has('includeNonVisiblePartners') && $request->input('includeNonVisiblePartners') == true) {
        $showOnlyWhiteLabelVisiblePartners = false;
    }

    $result = getGwAllPublicPartnersByRegion($request->input('region_name'), 'GGUID, COMPNAME, COMPNAME2, CATEGORY, STREET2, ZIP2, TOWN2, STREET1, ZIP1, TOWN1, GWLATITUDE, GWLONGITUDE, TMISTAUFLADESTELLE, TMISTEINLOESESTELLE, TMBONUSAKTIVIERT, TMARTDERPARTNERSCHAFT', $showOnlyWhiteLabelVisiblePartners, false, $excludeTestRecords);

    if(isError($result)){
        return $result;
    }

    if(empty($result)) {
        return [];
    }

    $response = [];
    foreach ($result as $company) {
        $temp = new stdClass();
        $temp->gguid = $company->GGUID;
        $temp->companyName = (property_exists($company, 'COMPNAME2') and !empty($company->COMPNAME2)) ? $company->COMPNAME2 : ((property_exists($company, 'COMPNAME') and !empty($company->COMPNAME)) ? $company->COMPNAME : '');
        $temp->street = (property_exists($company, 'STREET2') and !empty($company->STREET2)) ? $company->STREET2 : ((property_exists($company, 'STREET1') and !empty($company->STREET1)) ? $company->STREET1 : '');
        $temp->zip = (property_exists($company, 'ZIP2') and !empty($company->ZIP2)) ? $company->ZIP2 : ((property_exists($company, 'ZIP1') and !empty($company->ZIP1)) ? $company->ZIP1 : '');
        $temp->city = (property_exists($company, 'TOWN2') and !empty($company->TOWN2)) ? $company->TOWN2 : ((property_exists($company, 'TOWN1') and !empty($company->TOWN1)) ? $company->TOWN1 : '');
        $temp->categories = property_exists($company, 'CATEGORY') ? explode(', ', $company->CATEGORY) : [];
        $temp->logoUrl = 'https://backend.mycity.cards/api/v1/partners/' . $company->GGUID . '/logo.png';
        $temp->bonusActive = false;
        $temp->latitude = (property_exists($company, 'GWLATITUDE') and !empty($company->GWLATITUDE)) ?
            $company->GWLATITUDE : 0;
        $temp->longitude = (property_exists($company, 'GWLONGITUDE') and !empty($company->GWLONGITUDE)) ?
            $company->GWLONGITUDE : 0;
        $temp->canAddVoucher = property_exists($company, 'TMISTAUFLADESTELLE') ? $company->TMISTAUFLADESTELLE : false;
        $temp->canRedeemVoucher = property_exists($company, 'TMISTEINLOESESTELLE') ? $company->TMISTEINLOESESTELLE : false;
        $temp->type = property_exists($company, 'TMARTDERPARTNERSCHAFT') ? $company->TMARTDERPARTNERSCHAFT : null;
        array_push($response, $temp);
    }

    $gguids = array_column($result, 'GGUID');

    if(!empty($gguids) && count($gguids) > 0) {
        $gguids = array_map(fn($str) => '0x' . $str, $gguids); //prefix every gguid with "0x"
        $gguids = implode(',', $gguids);

        $gwBonusResponse = Http::withHeaders([
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
            'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
        ])->post(env('GW_API_BASE') . '/query', [
            "query" => "SELECT b.*, a.GGUID as ADDRESSGGUID FROM BONI b LINK_JOIN(linkattribute='TMBONUS') ADDRESS a WHERE a.GGUID IN (" .
                $gguids
                . ") AND b.GWSSTATUS = 'aktiviert' ORDER BY GWSTYPE"
        ]);

        if($gwBonusResponse->failed()) {
            Log::error('handleGetPartners, could not request boni from GW: \n\n' . $gwBonusResponse->body());
            return createErrorObject('Es ist ein unbekannter Fehler aufgetreten.', 'unknown_error', 500);
        }

        if(count(json_decode($gwBonusResponse)) == 0) {
            return $response;
        }

        $gwBonusData = json_decode($gwBonusResponse)[0]->rows;
        if(count($gwBonusData) == 0) {
            return $response;
        }
        foreach ($gwBonusData as $bonus) {
            foreach ($response as $company) {
                if($company->gguid == $bonus->ADDRESSGGUID) {
                    $company->bonusActive = true;
                    if(!property_exists($company, 'boni')) {
                        $company->boni = [];
                    }
                    $tempBoni = formatBoniObject($bonus);
                    array_push($company->boni, $tempBoni);
                }
            }
        }
    }

    return $response;
}

/**
 * @param mixed $bonus
 * @return stdClass
 */
function formatBoniObject(mixed $bonus): stdClass {
    $tempBoni = new stdClass();
    $tempBoni->type = $bonus->GWSTYPE;
    $bonusTypeLowercased = strtolower($bonus->GWSTYPE);
    if ($bonusTypeLowercased == 'aktionsbonus') {
        if (property_exists($bonus, 'TMGUELTIGAB')) {
            $tempBoni->startDate = $bonus->TMGUELTIGAB;
        }
        if (property_exists($bonus, 'TMGUELTIGBIS')) {
            $tempBoni->endDate = $bonus->TMGUELTIGBIS;
        }
    }
    if (property_exists($bonus, 'TMAUFGESAMTESSORTIMENT')) {
        $bonusForAllArticlesLowercased = trim(strtolower($bonus->TMAUFGESAMTESSORTIMENT));
        if ($bonusForAllArticlesLowercased == 'ja' || $bonusForAllArticlesLowercased == 'yes') {
            $tempBoni->description = 'Auf das gesamte Sortiment';
        } else {
            if (property_exists($bonus, 'TMBONUSNURAUF')) {
                $tempBoni->description = 'Nur auf: ' . $bonus->TMBONUSNURAUF;
            }
            if (property_exists($bonus, 'TMBONUSNICHTAUF')) {
                $tempBoni->description = 'Außer auf: ' . $bonus->TMBONUSNICHTAUF;
            }
        }
    } else {
        $tempBoni->description = 'Auf das gesamte Sortiment';
    }
    if (property_exists($bonus, 'TMBONUSART')) {
        $bonusartLowercased = strtolower($bonus->TMBONUSART);
        switch ($bonusartLowercased) {
            case 'prozentualer bonus vom einkaufswert':
                $tempBoni->title = tmFormatPercent($bonus->TMERSTATTUNGINPROZENT) . ' auf Ihren Einkauf';
                break;
            case 'prozentualer bonus vom einkaufswert in kombination mit einem mindestumsatz':
                $tempBoni->title = tmFormatPercent($bonus->TMERSTATTUNGINPROZENT) . ' auf Ihren Einkauf ab ' .
                    tmFormatCurrency($bonus->TMMINDESTUMSATZ) . ' Einkaufswert';
                break;
            case 'prozentualer bonus vom einkaufswert in kombination mit einem maximalumsatz':
                $tempBoni->title = tmFormatPercent($bonus->TMERSTATTUNGINPROZENT) . ' auf Ihren Einkauf bis ' .
                    tmFormatCurrency($bonus->TMMAXIMALUMSATZ) . ' Einkaufswert';
                break;
            case 'festbetrag':
                $tempBoni->title = tmFormatCurrency($bonus->TMERSTATTUNGSBETRAG) . ' auf Ihren Einkauf';
                break;
            case 'festbetrag in kombination mit einem mindestumsatz':
                $tempBoni->title = tmFormatCurrency($bonus->TMERSTATTUNGSBETRAG) . ' auf Ihren Einkauf ab ' .
                    tmFormatCurrency($bonus->TMMINDESTUMSATZ) . ' Einkaufswert';
                break;
            case 'festbetrag in kombination mit einem maximalumsatz':
                $tempBoni->title = tmFormatCurrency($bonus->TMERSTATTUNGSBETRAG) . ' auf Ihren Einkauf bis ' .
                    tmFormatCurrency($bonus->TMMAXIMALUMSATZ) . ' Einkaufswert';
                break;
        }
    }
    if (property_exists($bonus, 'TMZEITSTEUERUNGAKTIVIERT')) {
        $isBonusTimeActivated = false;
        $gwBonusTimeActivated = $bonus->TMZEITSTEUERUNGAKTIVIERT;
        if (is_string($gwBonusTimeActivated)) {
            $gwBonusTimeActivated = strtolower(trim($gwBonusTimeActivated));
            if ($gwBonusTimeActivated == 'ja' || $gwBonusTimeActivated == 'yes' ||
                $gwBonusTimeActivated == 'aktiviert') {
                $isBonusTimeActivated = true;
            }
        } else if (is_bool($gwBonusTimeActivated)) {
            $isBonusTimeActivated = $gwBonusTimeActivated;
        }
        if ($isBonusTimeActivated) {
            $tempBoni->startTime = gWDateToGermanTime($bonus->TMZEITSTEUERUNGGUELTIGAB);
            $tempBoni->endTime = gWDateToGermanTime($bonus->TMZEITSTEUERUNGGUELTIGBIS);
            if (property_exists($bonus, 'TMZEITSTEUERUNGANGABEN')) {
                $tempBoni->activeOnTimeText = trim($bonus->TMZEITSTEUERUNGANGABEN);
            }
        }
    }
    if (property_exists($bonus, 'TMGUELTIGANALLENTAGEN')) {
        $isBonusValidOnAllDays = false;
        $gwBonusValidOnAllDays = $bonus->TMGUELTIGANALLENTAGEN;
        if (is_string($gwBonusValidOnAllDays)) {
            $gwBonusValidOnAllDays = strtolower(trim($gwBonusValidOnAllDays));
            if ($gwBonusValidOnAllDays == 'ja' || $gwBonusValidOnAllDays == 'yes' ||
                $gwBonusValidOnAllDays == 'aktiviert') {
                $isBonusValidOnAllDays = true;
            }
        } else if (is_bool($gwBonusTimeActivated)) {
            $isBonusValidOnAllDays = $gwBonusValidOnAllDays;
        }
        if ($isBonusValidOnAllDays && property_exists($bonus, 'TMGUELTIGANWOCHENTAGEN')) {
            if (property_exists($tempBoni, 'activeOnTimeText')) {
                $tempBoni->activeOnTimeText = $bonus->TMGUELTIGANWOCHENTAGEN . ' jeweils ' .
                    $tempBoni->startTime . ' - ' . $tempBoni->endTime;
            } else {
                $tempBoni->activeOnTimeText = $bonus->TMGUELTIGANWOCHENTAGEN;
            }
        }
    }
    return $tempBoni;
}


Route::get('/maintenance-check', function (Request $request) {

    $maintenance_content = new stdClass();
    $maintenance_content->isMaintenanceActive = false;
    $maintenance_content->headline = '';
    $maintenance_content->content = '';
    $maintenance_content->type = '';

    if (Storage::exists('maintenance.json')) {
        $maintenance_content = Storage::json('maintenance.json');
        if($maintenance_content != NULL) {
            $maintenance_content = (object) $maintenance_content;
            if(property_exists($maintenance_content, 'isMaintenanceActive') && $maintenance_content->isMaintenanceActive == true) {
                if(property_exists($maintenance_content, 'start') && !empty($maintenance_content->start)) {
                    $now = new DateTime('now', new DateTimeZone('Europe/Berlin'));
                    $startDate = DateTime::createFromFormat('U', $maintenance_content->start, new DateTimeZone('Europe/Berlin'));
                    if(property_exists($maintenance_content, 'end') && !empty($maintenance_content->end)) {
                        $endDate = DateTime::createFromFormat('U', $maintenance_content->end, new DateTimeZone('Europe/Berlin'));
                        if($startDate > $now || $endDate < $now) {
                            $maintenance_content->isMaintenanceActive = false;
                        }
                    } else {
                        //if no end date
                        if($startDate > $now) {
                            $maintenance_content->isMaintenanceActive = false;
                        }
                    }
                }
            }
            return response()->json( $maintenance_content, 200 );
        }
    }

    return response()->json( $maintenance_content, 200 );
});

function addTerminal($companyID, $branchID) {

    $valueMasterResponse = Http::withHeaders([
        'provider' => 'trolleymaker',
        'password' => 'poiJJ#9q9'
    ])->post('https://valuemaster.brain-behind.com/CU_WebAPI.asmx/CU_Add_Terminal', [
        'CompanyID' =>  $companyID,
        'BranchID' => $branchID,
        'TerminalID' => 'W' . $companyID,
        'TerminalGroup' => '1212001',
        'Consumer' => true,
        'Producer' => false
    ]);

    $data = json_decode($valueMasterResponse)->d;

    if($data && $data != NULL) {
        if(($data->error && $data->error != '') || $data->status && $data->status == 'NOK') {
            Log::error('Error creating addTerminal: CompanyID: ' . $companyID . ', BranchID: ' . $branchID);
            return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'error_addTerminal', 500);
        }

        return $data;
    } else {
        Log::error('Error creating addTerminal, data is NULL: CompanyID: ' . $companyID . ', BranchID: ' . $branchID);
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'error_addTerminal', 500);
    }
}

function _getSuggestedValuesForFirebaseClients($fieldsToGet = []) {
    return _getSuggestedValues('FIREBASENUMMERN', $fieldsToGet);
}

function _getSuggestedValuesForPushNotifications($fieldsToGet = []) {
    return _getSuggestedValues('PUSH_NACHRICHTEN', $fieldsToGet);
}

function _getSuggestedValuesForAddress($fieldsToGet = []) {
    return _getSuggestedValues('address', $fieldsToGet);
}

function _getSuggestedValues($objectType, $fieldsToGet = []) {

    $getUrl = env('GW_API_BASE') . '/type/' . $objectType . '/schema';

    $gwResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->get($getUrl);

    if($gwResponse->failed()) {
        if($gwResponse->status() == 503) {
            return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error('_getSuggestedValues:\n\n' . $gwResponse->body());
            return createErrorObject('Es ist ein unbekannter Fehler aufgetreten.', 'unknown_error', 500);
        }
    }

    $gwSchemaData = json_decode($gwResponse);

    if(!property_exists($gwSchemaData, 'properties') || $gwSchemaData->properties == NULL) {
        Log::error('In _getSuggestedValues existierte die Property "properties" nicht.');
        return createErrorObject('Es ist ein unbekannter Fehler aufgetreten.', 'unknown_error', 500);
    }

    if(!property_exists($gwSchemaData->properties, 'fields') || $gwSchemaData->properties->fields == NULL) {
        Log::error('In _getSuggestedValues existierte die Property "fields" nicht.');
        return createErrorObject('Es ist ein unbekannter Fehler aufgetreten.', 'unknown_error', 500);
    }

    $fields = $gwSchemaData->properties->fields;
    $response = array();
    foreach ($fieldsToGet as $fieldName) {
        if(!property_exists($fields, $fieldName) || $fields->{$fieldName} == NULL) {
            Log::error('In _getSuggestedValues wurde kein Feld namens ' . $fieldName . ' gefunden.');
            return createErrorObject('Es ist ein unbekannter Fehler aufgetreten.', 'unknown_error', 500);
        }


        if(!property_exists($fields->{$fieldName}, 'rootNode') || $fields->{$fieldName}->rootNode == NULL || !property_exists($fields->{$fieldName}->rootNode, 'children') || $fields->{$fieldName}->rootNode->children == NULL) {
            if(!property_exists($fields->{$fieldName}, 'suggestedValues') || $fields->{$fieldName}->suggestedValues == NULL) {
                Log::error('In _getSuggestedValues wurde für das Feld namens ' . $fieldName . ' keine suggestedValues gefunden.');
                return createErrorObject('Es ist ein unbekannter Fehler aufgetreten.', 'unknown_error', 500);
            }

            $response[$fieldName] = $fields->{$fieldName}->suggestedValues;
        } else {
            $children = $fields->{$fieldName}->rootNode->children;
            $values = [];
            foreach ($children as $suggestedValue) {
                if(property_exists($suggestedValue, 'children') && count($suggestedValue->children) > 0) {
                    foreach ($suggestedValue->children as $childrenSuggestedValue) {
                        array_push($values, $suggestedValue->value . ' | ' . $childrenSuggestedValue->value);
                    }
                } else {
                    array_push($values, $suggestedValue->value);
                }
            }
            $response[$fieldName] = $values;
        }
    }

    return $response;
}

function _getSuggestedTypesForPushNotifications() {
    return _getSuggestedTypes('PUSH_NACHRICHTEN');
}

function _getSuggestedTypesForAddress() {
    return _getSuggestedTypes('ADDRESS');
}

function _getSuggestedTypes($objectType) {

    $getUrl = env('GW_API_BASE') . '/type/' . $objectType . '/schema';

    $gwResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->get($getUrl);

    if($gwResponse->failed()) {
        if($gwResponse->status() == 503) {
            return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error('_getSuggestedValues:\n\n' . $gwResponse->body());
            return createErrorObject('Es ist ein unbekannter Fehler aufgetreten.', 'unknown_error', 500);
        }
    }

    $gwSchemaData = json_decode($gwResponse);
    if(!property_exists($gwSchemaData, 'properties') || $gwSchemaData->properties == NULL) {
        Log::error('In _getSuggestedTypes existierte die Property "properties" nicht.');
        return createErrorObject('Es ist ein unbekannter Fehler aufgetreten.', 'unknown_error', 500);
    }

    if(!property_exists($gwSchemaData->properties, 'fields') || $gwSchemaData->properties->fields == NULL) {
        Log::error('In _getSuggestedTypes existierte die Property "fields" nicht.');
        return createErrorObject('Es ist ein unbekannter Fehler aufgetreten.', 'unknown_error', 500);
    }

    $fields = $gwSchemaData->properties->fields;

    $gwstypeResponse = array();
    $suggestedType = $fields->GWSTYPE->suggestedTypes;
    foreach ($suggestedType as $type) {
        array_push($gwstypeResponse, $type->value);
    }

    return $gwstypeResponse;
}


function _getGwFirebaseClientsForFirebaseIDs($select = '*', $firebaseIDs = NULL) {

    $validator = Validator::make([
        'firebaseIDs' => $firebaseIDs,
    ], [
        'firebaseIDs'   => 'required|array',
    ]);

    if ($validator->fails()) {
        Log::error('_getGwFirebaseClientsForFirebaseIDs: failed validation for $firebaseIDs: ' . print_r($firebaseIDs, true));
        return createErrorObject('Es ist ein unbekannter Fehler aufgetreten.', 'no_firebaseIDs', 400);
    }

    $query = 'SELECT ' . $select . ' FROM FIREBASENUMMERN WHERE FBFIREBASEID ';
    if(count($firebaseIDs) == 1) {
        $query .= '= "' . $firebaseIDs[0] . '"';
    } else {
        $query .= 'IN ("' . implode('", "', $firebaseIDs) . '")';
    }

    $gwResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/query', [
        'query' => $query
    ]);

    if($gwResponse->failed()) {
        if($gwResponse->status() == 503) {
            return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error('_getGwFirebaseClientsForFirebaseIDs: ' . $query . '\n\n' . $gwResponse->body());
            return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
        }
    }

    if(count(json_decode($gwResponse)) <= 0 || !property_exists(json_decode($gwResponse)[0], 'rows') || count(json_decode($gwResponse)[0]->rows) <= 0) {
        return NULL;
    } else {
        $gwFierbaseClientsData = json_decode($gwResponse)[0]->rows;
        Log::debug('_getGwFirebaseClientsForFirebaseIDs: ' . print_r($gwFierbaseClientsData, true));

        return $gwFierbaseClientsData;
    }
}

function getGWAllPushNotifications($select = '*') {

    $query = 'SELECT ' . $select . ' FROM PUSH_NACHRICHTEN';

    $gwResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/query', [
        'query' => $query
    ]);

    if($gwResponse->failed()) {
        if($gwResponse->status() == 503) {
            return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error('getGWAllPushNotifications: ' . $query . '\n\n' . $gwResponse->body());
            return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
        }
    }

    if(count(json_decode($gwResponse)) <= 0 || !property_exists(json_decode($gwResponse)[0], 'rows') || count(json_decode($gwResponse)[0]->rows) <= 0) {
        return NULL;
    } else {
        $gwPushNotifications = json_decode($gwResponse)[0]->rows;

        Log::debug('getGWAllPushNotifications: ' . print_r($gwPushNotifications, true));

        return $gwPushNotifications;
    }
}


function getGwFirebaseClientsForFirebaseIDs($select = '*', $firebaseIDs = NULL) {

    // TODO: Check if firebaseIDs are actually numeric values

    $validator = Validator::make([
        'firebaseIDs' => $firebaseIDs,
    ], [
        'firebaseIDs'   => 'required|array',
        'firebaseIDs.*' => 'numeric'
    ]);

    if ($validator->fails()) {
        return createErrorObject('Es wurden keine Firebase IDs angegeben.', 'no_firebaseIDs', 400);
    }

    $imploded_firebaseIds = implode(', ', $firebaseIDs);
    $query = 'SELECT ' . $select . ' FROM FIREBASENUMMERN WHERE FBFIREBASEID IN (' . $imploded_firebaseIds . ')';

    $gwResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/query', [
        'query' => $query
    ]);

    if($gwResponse->failed()) {
        if($gwResponse->status() == 503) {
            return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error('getGwFirebaseClientsForFirebaseIDs: ' . $query . '\n\n' . $gwResponse->body());
            return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
        }
    }

    if(count(json_decode($gwResponse)) <= 0 || !property_exists(json_decode($gwResponse)[0], 'rows') || count(json_decode($gwResponse)[0]->rows) <= 0) {
        return NULL;
    } else {
        $gwFierbaseClientsData = json_decode($gwResponse)[0]->rows[0];

        Log::debug('getGwFirebaseClientsForFirebaseIDs: ' . print_r($gwFierbaseClientsData, true));

        return $gwFierbaseClientsData;
    }
}


Route::get('/communities-for-region', function (Request $request) {

    if(!$request->has('regionName')) {
        Log::error("Bei /communities-for-region wurde keine Region mitgeschickt");
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 400);
    }

    if(empty($request->input('regionName'))) {
        Log::error("Bei /communities-for-region wurde eine leere Region mitgeschickt");
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 400);
    }

    $region = $request->input('regionName');

    $fieldsToGet = array('TMGEMEINDEZUGEHOERIGKEIT');

    $communities = _getSuggestedValuesForAddress($fieldsToGet);

    if(isError($communities)){
        return returnErrorObject($communities);
    }

    if(is_array($communities) && !array_key_exists('TMGEMEINDEZUGEHOERIGKEIT', $communities)) {
        Log::error("Es wurde keine Werte für Feld TMGEMEINDEZUGEHOERIGKEIT gefunden für die Region: " . $region . ',  Communities: ' . print_r($communities, true));
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Es wurde keine Gemeindezugehörigkeit gefunden. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
    }

    $responseToSend = [];
    if(is_array($communities)) {
        foreach ($communities['TMGEMEINDEZUGEHOERIGKEIT'] as $community) {
            $tempCommunity = new stdClass();
            if(str_starts_with($community, $region)) {
                $tempCommunity->value = $community;
                if(contains(' - ', $community)) {
                    $tempCommunity->label = str_replace($region . ' - ', '', $community);
                } else if(contains(' | ', $community)) {
                    $tempCommunity->label = str_replace($region . ' | ', '', $community);
                } else {
                    $tempCommunity->label = $community;
                }

                array_push($responseToSend, $tempCommunity);
            }
        }
    }

    if(count($responseToSend) === 0) {
        Log::error("Es wurde keine Gemeindezugehörigkeit gefunden für Region: " . $region . ',   Communities: ' . print_r($communities, true));
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Es wurde keine Gemeindezugehörigkeit gefunden. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
    }

    return response()->json($responseToSend, 200);
});




function _handleAddCard($request)
{
    // Eingabevalidierung
    if (!$request->has('newCardProductionNumber') || empty($request->input('newCardProductionNumber'))) {
        Log::error("Fehler beim Hinzufügen einer Karte: Es wurde keine Produktionsnummer angegeben.");
        return createErrorObject('Es wurde keine Produktionsnummer angegeben. Bitte überprüfen Sie Ihre Eingaben.', 'no_production_number', 400);
    }
    if (!$request->has('newCardToAdd') || empty($request->input('newCardToAdd'))) {
        Log::error("Fehler beim Hinzufügen einer Karte: Es wurde keine neue Kartennummer angegeben.");
        return createErrorObject('Es wurde keine Kartennummer angegeben. Bitte überprüfen Sie Ihre Eingaben.', 'no_new_card_to_add', 400);
    }

    $newCardToAdd = $request->input('newCardToAdd');
    $newCardProductionNumber = $request->input('newCardProductionNumber');

    // Abrufen von Kartendaten
    $cardData = getCardForCardID($newCardToAdd);

    if (isError($cardData)) {
        Log::error("Fehler beim Abrufen von Kartendaten für Karten-ID: " . print_r($newCardToAdd, true));
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
    }

    if ($cardData == NULL || !property_exists($cardData, 'GGUID')) {
        Log::error("Fehler beim Abrufen von Kartendaten für Karten-ID: " . print_r($newCardToAdd, true) . ". Karte nicht gefunden oder ungültig.");
        return createErrorObject('Die angegebene Kartennummer ist ungültig. Bitte überprüfen Sie Ihre Eingaben.', 'unknown_error', 500);
    }

    if ($cardData->KVWPRODUKTIONSNUMMER != $newCardProductionNumber) {
        Log::error("Fehler beim Hinzufügen einer Karte: Die angegebene Produktionsnummer ist nicht korrekt.");
        return createErrorObject('Die angegebene Produktionsnummer ist nicht korrekt. Bitte überprüfen Sie Ihre Eingaben.', 'invalid_production_number', 400);
    }

    if ($cardData->KVWKARTEREGISTRIERT == true) {
        Log::error("Die angegebene Kartennummer ist bereits registriert.");
        return createErrorObject('Die angegebene Kartennummer ist bereits registriert. Bitte wenden Sie sich an den Support.', 'already_registered', 400);
    }
    if ($cardData->GWSSTATUS !== 'aktiviert') {
        Log::error("Die angegebene Kartennummer nicht aktiviert.");
        return createErrorObject('Die angegebene Kartennummer ist nicht aktiviert. Bitte wenden Sie sich an den Support.', 'not_activated', 400);
    }
    if (str_contains($cardData->KVWMODUL, 'MitarbeiterCARD')) {
        $data = new stdClass();
        $data->email = $request->input('email');
        $data->cardNumber = $request->input('newCardToAdd');
        Mail::to('support@trolleymaker.com')->send(new AddedCardIsEmployeeCard($data));
    }
    if ($cardData->KVWREGION != $request->input('region_name')) {
        Log::error("Die angegebene Region entspricht nicht der Kartenregion.");
        return createErrorObject('Die angegebene Kartennummer ist ungültig. Bitte überprüfen Sie Ihre Eingaben.', 400);
    }

    $addressGguid = $request->input('contact_person_gguid');

    $addNewCardtoAdressResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='])
        ->post(env('GW_API_BASE') . '/type/ADDRESS/' . $addressGguid . '/dossier?gguid2=' . $cardData->GGUID . '&attribute=TMKVWADRESSE&object-type2=KARTENVERWALTUNG');

    if ($addNewCardtoAdressResponse->failed()) {
        Log::error("Fehler beim Verknüpfen der Karte: " . $addNewCardtoAdressResponse->body());
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
    }

    return response()->json(new stdClass(), 200);
}


Route::post('/add-card', function (Request $request) {
    $returnFromHandle = _handleAddCard($request);
    if(isError($returnFromHandle)) {
        return returnErrorObject($returnFromHandle);
    }

    return response()->json( $returnFromHandle, 200 );
})->middleware(['AuthenticateWithSession']);



function getGwNutzernameForEMail($select = '*', $email = NULL) {

    $validator = Validator::make([
        'email' => $email,
    ], [
        'email' => 'required|email'
    ]);

    if ($validator->fails()) {
        return createErrorObject('Es wurde keine E-Mail-Adresse angegeben.', 'no_email', 400);
    }

    $query = 'SELECT ' . $select . ' FROM NUTZERNAMEN WHERE TMNUTZERMAIL="' . $email . '"';

    //Log::error('getGwPersonalData: ' . $query);
    $gwResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
   ])->post(env('GW_API_BASE') . '/query', [
        'query' => $query
    ]);

    if($gwResponse->failed()) {
        if($gwResponse->status() == 503) {
            return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error('getGwNutzernameForEMail: ' . $query . '\n\n' . $gwResponse->body());
            return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
        }
    }

    if(count(json_decode($gwResponse)) <= 0 || !property_exists(json_decode($gwResponse)[0], 'rows') || count(json_decode($gwResponse)[0]->rows) <= 0) {
        return NULL;
    } else {
        $gwUserData = json_decode($gwResponse)[0]->rows[0];

        Log::debug('getGwNutzernameForEMail: ' . print_r($gwUserData, true));

        return $gwUserData;
    }
}


function getGwAddressForLoginEMail($select, $email = "") {

    $validator = Validator::make([
        'email' => $email,
    ], [
        'email' => 'required|email'
    ]);

    if ($validator->fails()) {
        return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
    }

    $query = 'SELECT ' . $select . ' FROM address WHERE (GWSTYPE="Kunde" AND MAILFIELDSTR3="' . $email . '") OR (TMADMINUSER="' . $email . '" AND (GWSTYPE="Partnerschaft" OR GWSTYPE="Partner" OR GWSTYPE="Interessent" OR GWSTYPE="Arbeitgeber (MitarbeiterCARD)" OR GWSTYPE="Auftraggeber" OR GWSTYPE="Lieferant"))';

    $gwResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/query', [
        'query' => $query
    ]);

    if($gwResponse->failed()) {
        if($gwResponse->status() == 503) {
            return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error('getGwAddressForLoginEMail: ' . $query . '\n\n' . $gwResponse->body());
            return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
        }
    }


    $gwUserData = json_decode($gwResponse);

    if(count($gwUserData) == 0 || count($gwUserData[0]->rows) == 0) {
        return NULL;
    }

    if(count($gwUserData) > 1 || count($gwUserData[0]->rows) > 1) {
        Log::error('getGwAddressForLoginEMail: Es wurden mehrere Adressen für die Login-Email-Adresse gefunden: ' . $query . '\n\n' . $gwResponse->body());
        sendErrorNotificationMail('Für die E-Mail-Adresse ' . $email . ' wurden mehrere Datensätze mit der gleichen Login-EMail-Adresse gefunden.');
        return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
    }

    Log::debug('getGwAddressForLoginEMail: ' . print_r($gwUserData, true));

    return $gwUserData[0]->rows[0];
}

function getGwCustomerPersonalDataForEMail($select, $email = "") {

    $validator = Validator::make([
        'email' => $email,
    ], [
        'email' => 'required|email'
    ]);

    if ($validator->fails()) {
        return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
    }

    $query = 'SELECT ' . $select . ' FROM address WHERE GWSTYPE="Kunde" AND MAILFIELDSTR3="' . $email . '"';

    //Log::error('getGwPersonalData: ' . $query);
    $gwResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/query', [
        'query' => $query
    ]);

    if($gwResponse->failed() || count(json_decode($gwResponse)) <= 0 || !property_exists(json_decode($gwResponse)[0], 'rows') || count(json_decode($gwResponse)[0]->rows) <= 0) {
        if($gwResponse->status() == 503) {
            return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error('getGwCustomerPersonalDataForEMail, customer data not found: ' . $query . '\n\n' . $gwResponse->body());
            return createErrorObject('Es ist ein Fehler aufgetreten. Evtl. ist Ihre Karte noch nicht registriert?', 'unknown_error', 500);
        }
    }

    $gwUserData = json_decode($gwResponse)[0]->rows[0];

    return $gwUserData;
}

function getGwAllPublicPartnersByRegion($region, $select = "*", $showOnlyWhiteLabelVisible = false, $showOnlyPersonalDataCompletePartners = false, $excludeTestRecords = true) {

    $validator = Validator::make([
        'region' => $region,
    ], [
        'region' => 'required|regex:/[\pL\-+\s*_]*$/'
    ]);

    if ($validator->fails()) {
        Log::error('getGwAllPublicPartnersByRegion error: error validating region name');
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
    }

    if($select != '*' && !contains('COMPNAME', $select)) {
        $select .= ', COMPNAME';
    }
    $query = 'SELECT ' . $select . ' FROM address WHERE TMVERTRAGSSTATUSPARTNER="aktiv" AND GWSTYPE="Partnerschaft" AND (TMARTDERPARTNERSCHAFT="Partner" OR TMARTDERPARTNERSCHAFT="Partner und Auftraggeber") AND TMMODULEPARTNER LIKE "%GutscheinCARD%" AND NCREGION="' . $region. '" AND GWISCOMPANY = true';
    if($showOnlyWhiteLabelVisible == true) {
        $query .= ' AND TMWLSICHTBAR = true';
    }
    if($showOnlyPersonalDataCompletePartners == true) {
        $query .= ' AND TMPARTNERDATENVOLLSTAENDIG = true';
    }
    if($excludeTestRecords == true) {
        $query .= ' AND TMISTTESTDATENSATZ != true';
    }
    $query .= ' ORDER BY COMPNAME';

    $gwResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/query', [
        'query' => $query
    ]);

    if($gwResponse->failed()) {
        $error_obj = new stdClass();
        if($gwResponse->status() == 503) {
            return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error('getGwPartnerByRegion: ' . $query . '\n\n' . $gwResponse->body());
            return createErrorObject('Es ist ein unbekannter Fehler aufgetreten.', 'unknown_error', 500);
        }

        return $error_obj;
    }

    if(count(json_decode($gwResponse)) == 0) {
        return [];
    }

    $gwPartnerData = json_decode($gwResponse)[0]->rows;

    Log::debug('getGwPartnerByRegion: ' . print_r($gwPartnerData, true));

    return $gwPartnerData;
}

function getGwInterestAndPartnerPersonalData($select, $email, $isContact = NULL, $isCompany = NULL) {

    $validator = Validator::make([
        'email'     => $email,
        'isContact' => $isContact,
        'isCompany' => $isCompany
    ], [
        'email'     => 'required|email',
        'isContact' => 'nullable|boolean',
        'isCompany' => 'nullable|boolean'
    ]);

    if ($validator->fails()) {
        return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
    }

    $query = 'SELECT ' . $select . ' FROM address WHERE TMADMINUSER="' . $email . '" AND ((GWSTYPE="Partnerschaft" AND (TMARTDERPARTNERSCHAFT="Partner" OR TMARTDERPARTNERSCHAFT="Partner und Auftraggeber") AND (TMMODULEPARTNER LIKE "%GutscheinCARD%" OR TMMODULEPARTNER LIKE "%MitarbeiterCARD%")) OR GWSTYPE="Interessent")';
    if($isContact != NULL) {
        $query .= ' AND GWISCONTACT=' . $isContact;
    }
    if($isCompany != NULL) {
        $query .= ' AND GWISCOMPANY=' . $isCompany;
    }

    $gwResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/query', [
        'query' => $query
    ]);

    if($gwResponse->failed() || count(json_decode($gwResponse)) <= 0 || !property_exists(json_decode($gwResponse)[0], 'rows') || count(json_decode($gwResponse)[0]->rows) <= 0) {
        if($gwResponse->status() == 503) {
            return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error("Fehler beim Abrufen von getGwInterestAndPartnerPersonalData: " . $query . " \n " . print_r($gwResponse->body(), true));
            return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
        }
    }

    $gwUserData = json_decode($gwResponse)[0]->rows[0];

    //Log::error('getGwInterestAndPartnerPersonalData: ' . print_r($gwUserData, true));

    return $gwUserData;
}

function getGwUserGguid($email)
{

        $validator = Validator::make(
            [
                'email' => $email,
            ],
            [
                'email' => 'required|email',
            ]);

        if ($validator->fails()) {
                return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
        }
        $query = 'SELECT * FROM NUTZERNAMEN WHERE TMNUTZERMAIL="' . $email . '"';


        $gwResponse = Http::withHeaders(
            [
                'Content-Type'      => 'application/json; charset=utf-8',
                'Accept'            => 'application/json',
                'Authorization'     => 'Basic ' . env("GW_AUTHORIZATION"),
                'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA==',
            ])->post(
            env('GW_API_BASE') . '/query',
            [
                'query' => $query,
            ]);


        $data = $gwResponse->json();

        if (!isset($data[0]['rows']) || count($data[0]['rows']) === 0) {
                Log::error("Error: user has no data in nutzernamen table");

                return NULL;
        }

        $rows = $data[0]['rows'];

        if (!isset($rows[0]['GGUID']) || empty($rows[0]['GGUID'])) {
                Log::error("Error: user has no GGUID in nutzernamen table");

                return NULL;
        }


        $userData = $rows[0];

        return $userData;


}

function getGwContractorPersonalData($select, $email, $isContact = NULL, $isCompany = NULL) {

    $validator = Validator::make([
        'email'     => $email,
        'isContact' => $isContact,
        'isCompany' => $isCompany
    ], [
        'email'     => 'required|email',
        'isContact' => 'nullable|boolean',
        'isCompany' => 'nullable|boolean'
    ]);

    if ($validator->fails()) {
        return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
    }


    $query = 'SELECT ' . $select . ' FROM address WHERE GWSTYPE="Partnerschaft" AND (TMARTDERPARTNERSCHAFT="Auftraggeber" OR TMARTDERPARTNERSCHAFT="Partner und Auftraggeber")';
    //if($isContact != NULL) {
    // $query .= ' AND GWISCONTACT=' . $isContact;
    // }
    //  if($isCompany != NULL) {
    //$query .= ' AND GWISCOMPANY=' . $isCompany;
    //}

    $gwResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/query', [
        'query' => $query
    ]);

    if($gwResponse->failed() || count(json_decode($gwResponse)) <= 0 || !property_exists(json_decode($gwResponse)[0], 'rows') || count(json_decode($gwResponse)[0]->rows) <= 0) {
        if($gwResponse->status() == 503) {
            return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error("Fehler beim Abrufen von getGwContractorPersonalData: " . print_r($gwResponse->body(), true));
            return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
        }
    }

    $gwUserData = json_decode($gwResponse)[0]->rows[0];

    Log::error('getGwContractorPersonalData: ' . print_r($gwUserData, true));

    return $gwUserData;
}

function getGwPersonalDataByGGUID($gguid) {

    $gwResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->get(env('GW_API_BASE') . '/type/address/' . $gguid);

    if($gwResponse->failed() || !property_exists(json_decode($gwResponse), 'fields')) {
        $error_obj = new stdClass();
        if($gwResponse->status() == 503) {
            return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error("Fehler beim Abrufen von getGwPersonalDataByGGUID für GGUID: " . $gguid . ": " . print_r($gwResponse->body(), true));
            return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
        }
        return $error_obj;
    }

    $gwPersonalData = json_decode($gwResponse)->fields;

    return $gwPersonalData;
}


function getGwTransactionByGGUID($transactionGguid) {

    $gwResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->get(env('GW_API_BASE') . '/type/transaktionsdaten/' . $transactionGguid);

    if($gwResponse->failed() || !property_exists(json_decode($gwResponse), 'fields')) {
        $error_obj = new stdClass();
        if($gwResponse->status() == 503) {
            return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error("Fehler beim Abrufen von getGwTransactionByGGUID für GGUID: " . $transactionGguid . ": " . print_r($gwResponse->body(), true));
            return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
        }
        return $error_obj;
    }

    $gwTransactionData = json_decode($gwResponse)->fields;

    return $gwTransactionData;
}


function getGwBranchUsers($gguid) {

    $getGwLinkedBranchUsers = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/query', [
        'query' => 'SELECT * FROM address WHERE PRIMARYORGANISATION=0x' . $gguid . ' AND GWSTYPE="Partnerschaft" AND (TMARTDERPARTNERSCHAFT="Partner" OR TMARTDERPARTNERSCHAFT="Partner und Auftraggeber") AND TMMODULEPARTNER LIKE "%GutscheinCARD%" AND GWISCONTACT=true ORDER BY INSERTTIMESTAMP'
    ]);

    if ($getGwLinkedBranchUsers->failed() || count(json_decode($getGwLinkedBranchUsers)) <= 0 || !property_exists(json_decode($getGwLinkedBranchUsers)[0], 'rows') || count(json_decode($getGwLinkedBranchUsers)[0]->rows) <= 0) {
        if ($getGwLinkedBranchUsers->status() == 503) {
            return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error("Fehler beim Abrufen von getGwBranchUsers. Error response: " . print_r($getGwLinkedBranchUsers->body(), true));
            return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
        }
    }

    return json_decode($getGwLinkedBranchUsers)[0]->rows;
}


function getGwContactPersonsForCompanyGGUID($company_gguid) {

    if(!empty($company_gguid) && str_starts_with($company_gguid, '0x')) {
        $company_gguid = str_replace('0x', '', $company_gguid);
    }

    $getGwContactPersons = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/query', [
        'query' => 'SELECT * FROM address WHERE PRIMARYORGANISATION=0x' . $company_gguid . ' AND GWISCONTACT=true ORDER BY INSERTTIMESTAMP'
    ]);

    if ($getGwContactPersons->failed() || count(json_decode($getGwContactPersons)) <= 0 || !property_exists(json_decode($getGwContactPersons)[0], 'rows') || count(json_decode($getGwContactPersons)[0]->rows) <= 0) {
        if ($getGwContactPersons->status() == 503) {
            return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error("Fehler beim Abrufen von getGwContactPersons. Error response: " . print_r($getGwContactPersons->body(), true));
            return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
        }
    }

    return json_decode($getGwContactPersons)[0]->rows;
}

function getRegionData($region_name, $card_name = NULL, $fieldsToGet = [], ) {

    if(($region_name == NULL && $card_name == NULL) || ($region_name == '' && $card_name == '')) {
        Log::error('Bei getRegionData wurde keine region_name und kein card_name mitgeschickt: region: ' . print_r($region_name, true) . ', card_name: ' . print_r($card_name, true));
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'no_region_name_and_card_name', 500);
    }

    $url = config('services.wordpress.regions.endpoint');
    $parameters = [];
    if($fieldsToGet != NULL && is_array($fieldsToGet) && count($fieldsToGet) > 0) {
        $parameters['_fields'] = implode(',', $fieldsToGet);
    }

    if($region_name != NULL && $region_name != '') {
        $parameters['region_name'] = $region_name;
    }

    if($card_name != NULL && $card_name != '') {
        $parameters['card_name'] = $card_name;
    }

    $url .= http_build_query($parameters);

    $getRegionData = Http::withoutVerifying()->withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
    ])->get($url);

    if($getRegionData->failed()) {
        Log::error('Fehler bei getRegionData Response: ' . $getRegionData->body());
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support', 'unknown_error', 500 );
    }

    $regionData = json_decode($getRegionData);
    if($regionData && count($regionData) > 1) {
        Log::error('Die Region konnte nicht eindeutig zugeordnet werden: region_name: ' . $region_name . ', card_name: ' . $card_name);
        return createErrorObject('Es ist ein Fehler aufgetreten. Die Region konnte nicht eindeutig zugeordnet werden. Bitte wenden Sie sich an den Support.', 'region_not_unique', 400 );
    } else {
        $regionData = $regionData[0];
        return $regionData;
    }
}


function checkIfEMailExists($email) {

    $email = trim($email);

    $checkInValueMasterIfEmailAlreadyExists = Http::withHeaders([
        'provider' => 'trolleymaker',
        'password' => 'poiJJ#9q9'
    ])->post('https://valuemaster.brain-behind.com/CU_WebAPI.asmx/CU_checkIfCustomerExists', [
        'searchKey' =>  'E-Mail',
        'searchKeyvalue' =>  $email
    ]);

    if($checkInValueMasterIfEmailAlreadyExists->failed() || $checkInValueMasterIfEmailAlreadyExists == NULL) {
        return createErrorObject('Es ist ein Fehler aufgetreten. Es konnte nicht geprüft werden, ob die E-Mail-Adresse bereits registriert wurde. Bitte wenden Sie sich an den Support.', 'unkown_error', 500);
    }

    if($checkInValueMasterIfEmailAlreadyExists && $checkInValueMasterIfEmailAlreadyExists != NULL) {
        $exists_data = json_decode($checkInValueMasterIfEmailAlreadyExists)->d;

        if($exists_data && $exists_data != NULL) {

            if(property_exists($exists_data, 'exists') && $exists_data->exists === true) {
                return true;
            }
        } else {
            return createErrorObject('Es ist ein Fehler aufgetreten. Es konnte nicht geprüft werden, ob die E-Mail-Adresse bereits registriert wurde. Bitte wenden Sie sich an den Support.', 'unkown_error', 500);
        }
    } else {
        return createErrorObject('Es ist ein Fehler aufgetreten. Es konnte nicht geprüft werden, ob die E-Mail-Adresse bereits registriert wurde. Bitte wenden Sie sich an den Support.', 'unkown_error', 500);
    }


    $checkIfEmailAlreadyRegistered = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/query', [
        'query' => 'SELECT NAME FROM address WHERE (MAILFIELDSTR5="' . $email . '" OR MAILFIELDSTR3="' . $email . '" OR TMADMINUSER="' . $email . '") AND (GWSTYPE="Partnerschaft" OR GWSTYPE="Partner" OR GWSTYPE="Kunde" OR GWSTYPE="Arbeitgeber (MitarbeiterCARD)" OR GWSTYPE="Interessent")'
    ]);

    if($checkIfEmailAlreadyRegistered->failed()) {
        Log::error('Es ist ein Fehler aufgetreten. Es konnte nicht geprüft werden, ob der Account/E-Mail-Adresse bereits registriert wurde: ' . $checkIfEmailAlreadyRegistered->body());
        return createErrorObject('Es ist ein Fehler aufgetreten. Es konnte nicht geprüft werden, ob der Account/E-Mail-Adresse bereits registriert wurde. Bitte wenden Sie sich an den Support.', 'unkown_error', 500);
    }

    $dataAlreadyRegistered = json_decode($checkIfEmailAlreadyRegistered);

    if($dataAlreadyRegistered && count($dataAlreadyRegistered) > 0) {
        return true;
    }

    return false;
}

Route::post('/email-exists-check', function (Request $request) {

    if(!$request->has('inputEmail') || $request->input('inputEmail') == '') {
        return returnNewErrorObject('Es wurde keine E-Mail-Adresse angegeben', 'no_email', 400);
    }

    $emailToCheck = trim($request->input('inputEmail'));



    $emailAlreadyExists = checkIfEMailExists($emailToCheck);
    if(isError($emailAlreadyExists)) {
        return returnErrorObject($emailAlreadyExists);
    }
    if($emailAlreadyExists == true) {
        return response()->json( ['exists' => true], 200 );
    } else {
        return response()->json( ['exists' => false], 200 );
    }

})->middleware(['AuthenticateWithSession']);


function getDocumentsForCompany($companyGguid, $documentTypesToSelect = NULL, $fileTypeToSelect = NULL, $statusToSelect = NULL) {

    $gwResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->get(env('GW_API_BASE') . '/type/document/full?linked-to=0x' . $companyGguid .'&linked-to-type=ADDRESS&order-by=DOCDATE desc');

    if($gwResponse->failed()) {
        if($gwResponse->status() == 503) {
            return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error("Fehler beim Abrufen von getDocumentsForCompany: " . print_r($gwResponse->body(), true));
            return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
        }
    }

    $documents = json_decode($gwResponse);

    $responseArray = array();

    if(count($documents) > 0) {
        foreach ($documents as $document) {
            $row = $document->fields;
            $response = new stdClass();

            if(!property_exists($row, 'GGUID') || !property_exists($row, 'GWFILETYPE') || !property_exists($row, 'GWSTYPE') || !property_exists($row, 'DOCDATE')) {
                continue;
            }

            if($documentTypesToSelect != NULL) {
                if(is_array($documentTypesToSelect)) {
                    $documentTypesToSelect = array_map('strtolower', $documentTypesToSelect);
                    if(!in_array(strtolower($row->GWSTYPE), $documentTypesToSelect)) {
                        continue;
                    }
                } else if(is_string($documentTypesToSelect)) {
                    if(strtolower($row->GWSTYPE) != strtolower($documentTypesToSelect)) {
                        continue;
                    }
                } else {
                    Log::error('Fehler beim Abrufen von getDocumentsForCompany: Es wurde kein gültiger documentTypesToSelect angegeben.');
                    return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unkown_error', 500);
                }
            }

            if($fileTypeToSelect != NULL) {
                if(is_array($fileTypeToSelect)) {
                    $fileTypeToSelect = array_map('strtolower', $fileTypeToSelect);
                    if(!in_array(strtolower($row->GWFILETYPE), $fileTypeToSelect)) {
                        continue;
                    }
                } else if(is_string($fileTypeToSelect)) {
                    if(strtolower($row->GWFILETYPE) != strtolower($fileTypeToSelect)) {
                        continue;
                    }
                } else {
                    Log::error('Fehler beim Abrufen von getDocumentsForCompany: Es wurde kein gültiger fileTypeToSelect angegeben.');
                    return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unkown_error', 500);
                }
            }

            if($statusToSelect != NULL) {
                if(!property_exists($row, 'GWSSTATUS')) {
                    continue;
                }
                if(is_array($statusToSelect)) {
                    $statusToSelect = array_map('strtolower', $statusToSelect);
                    if(!in_array(strtolower($row->GWSSTATUS), $statusToSelect)) {
                        continue;
                    }
                } else if(is_string($statusToSelect)) {
                    if(strtolower($row->GWSSTATUS) != strtolower($statusToSelect)) {
                        continue;
                    }
                } else {
                    Log::error('Fehler beim Abrufen von getDocumentsForCompany: Es wurde kein gültiger statusToSelect angegeben.');
                    return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unkown_error', 500);
                }
            }


            $response->documentType = $row->GWSTYPE;
            $response->fileType = $row->GWFILETYPE;
            $response->name = property_exists($row, 'NOTES') ? $row->NOTES : '';
            $response->date = gWDateToGermanDate($row->DOCDATE);
            $response->gguid = $row->GGUID;

            array_push($responseArray, $response);
        }
    }

    return $responseArray;
}

function _createAddressInGw($fields) {
    return _createRecordInGw($fields, 'ADDRESS');
}

function _createCardInGw($fields) {
    return _createRecordInGw($fields, 'KARTENVERWALTUNG');
}

function _createPushNotificationInGw($fields) {
    return _createRecordInGw($fields, 'PUSH_NACHRICHTEN');
}

function _createFirebaseClientInGw($fields) {
    return _createRecordInGw($fields, 'FIREBASENUMMERN');
}

function _createOpportunityInGw($fields) {
    return _createRecordInGw($fields, 'GWOPPORTUNITY');
}

function _createSuaItemInGw($fields) {
    return _createRecordInGw($fields, 'SUAITEMS');
}

/**
 * Creates a record / dataset in GW of a specific type
 *
 * @param  mixed $fields - the fields to set
 * @param  mixed $objectType - the type of record to create
 * @return string GGUID of the created record
 */
function _createRecordInGw($fields, $objectType)
{

    $gwCreateResponse = Http::withHeaders([
                                              'Content-Type'      => 'application/json; charset=utf-8',
                                              'Accept'            => 'application/json',
                                              'Authorization'     => 'Basic ' . env("GW_AUTHORIZATION"),
                                              'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
                                          ]
    )->post(env('GW_API_BASE') . '/type/' . $objectType, [
        'fields' => $fields
    ]
    );

    if ($gwCreateResponse->failed()) {
        Log::error("Fehler bei _createRecordInGw: " . $gwCreateResponse->body());

        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support', 'unknown_error', 500);
    }

    if ($gwCreateResponse->header('Location') == NULL || $gwCreateResponse->header('Location') == '') {
        Log::error("Fehler bei der Erstellung des Datensatzes in gW, Location Header für GGUID nicht vorhanden: " . $gwCreateResponse->body());

        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support', 'unknown_error', 500);
    } else {
        $location_splitted = explode("/", $gwCreateResponse->header('Location'));
        $gguid             = end($location_splitted);

        return $gguid;
    }
}

function getCardForCardID($cardID) {

    $validator = Validator::make([
        'cardID'     => $cardID,
    ], [
        'cardID'     => 'required|alpha_num',
    ]);

    if ($validator->fails()) {
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'no_cardID', 400);
    }

    if(!isValidCardIDSyntax($cardID)) {
        Log::error("Fehler beim Abrufen von getCardForCardID: Es wurde keine gültige cardID angegeben");
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'no_cardID', 400);
    }

    $query = "SELECT * FROM kartenverwaltung WHERE KVWKARTENNUMMER='" . $cardID . "' AND GWSTYPE='Karte'";

    $gwResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/query', [
        "query" => $query
    ]);

    if($gwResponse->failed()) {
        if($gwResponse->status() == 503) {
            return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error("Fehler beim Abrufen von getCardForCardID: " . $query . "\n" . print_r($gwResponse->body(), true));
            return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
        }
    }

    if(count(json_decode($gwResponse)) <= 0 || !property_exists(json_decode($gwResponse)[0], 'rows') || count(json_decode($gwResponse)[0]->rows) <= 0) {
        return new stdClass();
    }

    $rows = json_decode($gwResponse)[0]->rows;

    if(count($rows) > 1) {
        Log::error("Fehler beim Abrufen von getCardForCardID: Es wurden mehrere Karten für Kartennummer " . $cardID  . " gefunden: " . print_r($rows, true));
        sendErrorNotificationMail('Fehler beim Abrufen von getCardForCardID: Es wurden mehrere Karten für Kartennummer ' . $cardID  . ' gefunden: ' . print_r($rows, true));
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'multiple_cardIDs', 500);
    }

    $gwCardData = json_decode($gwResponse)[0]->rows[0];

    return $gwCardData;
}

function getCardsForCardIDs($cardIDs) {
    foreach ($cardIDs as $cardID) {
        if(!isValidCardIDSyntax($cardID)) {
            Log::error("Fehler beim Abrufen von getCardsForCardIDs : Eine der cardIDs ist ungültig");
            return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'invalid_cardID', 400);
        }
    }

    $gwResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/query', [
        'query' => 'SELECT KVWKARTENNUMMER, KVWKARTEREGISTRIERT FROM kartenverwaltung WHERE KVWKARTENNUMMER IN (' . implode(',',$cardIDs) . ')'
    ]);

    if($gwResponse->failed()) {
        if($gwResponse->status() == 503) {
            return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error("Fehler beim Abrufen von getCardsForCardIDs: " . print_r($gwResponse->body(), true));
            return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
        }
    }

    Log::debug(json_decode($gwResponse)[0]->rows);

    return json_decode($gwResponse)[0]->rows;
}



function getCustomerForCardID($cardID) {

    if(!$cardID || empty($cardID) || !is_numeric($cardID)) {
        Log::error("Fehler beim Abrufen von getCustomerForCardID: Es wurde keine gültige cardID angegeben");
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unkown_error', 500);
    }

    $gwCardData = getCardForCardID($cardID);

    if(isError($gwCardData)) {
        return $gwCardData;
    }

    if(!property_exists($gwCardData, 'GGUID')) {
        return createErrorObject('Kartennummer nicht gefunden. Bitte wenden Sie sich an den Support', 'unknown_error', 500);
    }

    return getCustomerForCardGGUID($gwCardData->GGUID);
}


function getCustomerForCardGGUID($cardGguid) {
    return _getAddressForCardGGUID($cardGguid, 'TMKVWADRESSE');
}

function getEmployerForCardGGUID($cardGguid) {
    return _getAddressForCardGGUID($cardGguid, 'TMKARTEARBEITGE');
}

function _getAddressForCardGGUID($cardGguid, $linkedToAttribute) {

    if($cardGguid == null || empty($cardGguid)) {
        Log::error("Fehler beim Abrufen von getAddressForCardGGUID: Es wurde keine cardGguid angegeben");
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unkown_error', 500);
    }

    if($linkedToAttribute == null || empty($linkedToAttribute)) {
        Log::error("Fehler beim Abrufen von getAddressForCardGGUID: Es wurde keine linkedToAttribute angegeben");
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unkown_error', 500);
    }

    $gwResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->get(env('GW_API_BASE') . '/type/address/full?linked-to=' . $cardGguid .'&linked-to-type=KARTENVERWALTUNG&linked-to-attributes=' . $linkedToAttribute);

    if($gwResponse->failed()) {
        if($gwResponse->status() == 503) {
            return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error("Fehler beim Abrufen von getAddressForCardGGUID: " . print_r($gwResponse->body(), true));
            return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unkown_error', 500);
        }
    }

    $address = json_decode($gwResponse);

    if(!$address || count($address) !== 1) {
        Log::error("Fehler beim Abrufen von getAddressForCardGGUID (" . $cardGguid . "): Es wurde keine Adressen oder mehrere Adressen gefunden: " . print_r($gwResponse->body(), true));
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unkown_error', 500);
    }

    return $address[0]->fields;
}




function getCardsForCustomer($addressGguid, $showOnlyActivatedCards = true) {
    return getCardsForAddressGGUID($addressGguid, 'TMKVWADRESSE', $showOnlyActivatedCards);
}

function getCardsForEmployer($addressGguid, $showOnlyActivatedCards = true) {
    return getCardsForAddressGGUID($addressGguid, 'TMKARTEARBEITGE', $showOnlyActivatedCards);
}


function getCardsForAddressGGUID($addressGguid, $linkedToAttribute, $showOnlyActivatedCards = true) {

    if($addressGguid == null || empty($addressGguid)) {
        Log::error("Fehler beim Abrufen von getCardsForAddressGGUID: Es wurde keine addressGguid angegeben");
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'no_addressGguid', 400);
    }

    if($linkedToAttribute == null || empty($linkedToAttribute)) {
        Log::error("Fehler beim Abrufen von getCardsForAddressGGUID: Es wurde keine linkedToAttribute angegeben");
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'no_linkedToAttribute', 400);
    }

    $gwResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->get(env('GW_API_BASE') . '/type/kartenverwaltung/full?linked-to=' . $addressGguid .'&linked-to-type=ADDRESS&linked-to-attributes=' . $linkedToAttribute);

    if($gwResponse->failed()) {
        if($gwResponse->status() == 503) {
            return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error("Fehler beim Abrufen von getCardsForAddressGGUID: " . print_r($gwResponse->body(), true));
            return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
        }
    }

    $cards = json_decode($gwResponse);

    /*
    if(!$cards || count($cards) <= 0) {
        Log::error("Fehler beim Abrufen von getCardsForAddressGGUID: Es wurde keine Karten für die Adresse " . $addressGguid . " gefunden: " . print_r($gwResponse->body(), true));
        return createErrorObject('Für Ihr Account wurden keine CARDs gefunden.', 'no_cards', 400);
    }
    */

    $responseArray = array();
    if(count($cards) > 0) {
        if($showOnlyActivatedCards == true) {
            foreach ($cards as $card) {
                $cardData = $card->fields;

                if(property_exists($cardData, 'GWSSTATUS') && strtolower($cardData->GWSSTATUS) == 'deaktiviert') {
                    continue;
                }
                if(property_exists($cardData, 'GWSTYPE') && strtolower($cardData->GWSTYPE) == 'archiv karten') {
                    continue;
                }
                if(property_exists($cardData, 'KVWKARTENSPERRUNG') && $cardData->KVWKARTENSPERRUNG == true) {
                    continue;
                }

                array_push($responseArray, $cardData);
            }
        } else {
            $responseArray = array_column($cards, 'fields');
        }
    }


    return $responseArray;
}

Route::post('/customer-login', function (Request $request) {
    $customerLogin = _handleCustomerLogin($request, false);

    if(isError($customerLogin)) {
        return returnErrorObject($customerLogin);
    }

    $response = new stdClass();
    $response->cardIDs = $customerLogin->cards;
    $response->region = $customerLogin->region;
    $response->cardName = $customerLogin->cardName;
    $response->gguid = $customerLogin->gguid;
    $response->role = $customerLogin->role;

    return response()->json( $response, 200 )->cookie('X-Authorization', $customerLogin->session_token, 1440, '/', $request->getHost(), $customerLogin->secure_cookie, true);
});

function _handleCustomerLogin($request, $loginFromAPI) {

    $cardID = trim($request->input('cardID'));
    $password = $request->input('password');

    if(!$cardID || empty($cardID)) {
        return createErrorObject('Es wurde keine E-Mail-Adresse oder Kartennummer angegeben', 'no_mail or no_cardID', 400 );
    }
    if(!$password || empty($password)) {
        return createErrorObject('Es wurde kein Passwort angegeben', 'no_password', 400 );
    }

    $email = NULL;

    if(is_numeric($cardID) && str_starts_with($cardID, '1761')) {
        //user entered cardnumber as login
        $personal_data = getCustomerForCardID($cardID);
    } else {
        //user entered email as login


            $email = $cardID;
        $personal_data = getGwCustomerPersonalDataForEMail('*', $email);
    }

    if(!property_exists($personal_data, 'GGUID') || !property_exists($personal_data, 'GWSTYPE')) {
        Log::error("error in handleCustomerLogin: Es wurde kein Datensatz gefunden. Eingabe: " . $cardID);
        return createErrorObject('Es existiert kein Account mit dieser E-Mail-Adresse oder Kartennummer.', 'unknown_error', 500 );
    }

    if(!property_exists($personal_data, 'MAILFIELDSTR3') || $personal_data->MAILFIELDSTR3 == '' || strlen($personal_data->MAILFIELDSTR3) <= 0) {
        Log::error("Kein Feld MAILFIELDSTR3 für E-Mail " . $email );
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support', 'unknown_error', 500 );
    }

    $email = $personal_data->MAILFIELDSTR3;


    $processedCustomerLogin = _processCustomerLogin($cardID, $password, $email, $personal_data->GGUID);
    if(isError($processedCustomerLogin)) {
        return $processedCustomerLogin;
    }


    $white_label_website_url = NULL;

    $getRegionData = Http::withoutVerifying()->withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
    ])->get(config('services.wordpress.regions.endpoint') . '_fields=acf.white_label_website_url,acf.terminalgroupid_gutschein,acf.terminalgroupid_mitarbeitercard&region_name=' . $personal_data->NCREGION);

    if($getRegionData->failed()) {
        Log::error($getRegionData->body());
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support', 'unknown_error', 500 );
    }

    $regionData = json_decode($getRegionData);
    if(!$regionData || count($regionData) == 0 || ($regionData && count($regionData) > 1)) {
        Log::error('Die Region konnte nicht eindeutig zugeordnet werden' . $personal_data->NCREGION);
        return createErrorObject('Es ist ein Fehler aufgetreten. Die Region konnte nicht eindeutig zugeordnet werden. Bitte wenden Sie sich an den Support.', 'region_not_unique', 400 );
    } else {
        $regionData = $regionData[0]->acf;
        $white_label_website_url = $regionData->white_label_website_url;
    }

    $session_token = (string) Str::uuid();

    $user_role = NULL;
    if($personal_data->GWSTYPE == 'Kunde') {
        $user_role = UserRoles::CUSTOMER;
    }

    if($user_role == NULL) {
        Log::error("Email " . $email . " hat Kunden-Login benutzt, aber Datensatz hat kein GWSTYPE");
        sendErrorNotificationMail('Email ' . $email . ' hat Kunden-Login benutzt, aber Datensatz hat kein GWSTYPE');
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support', 'unknown_error', 500 );
    }

    $returnObject = new stdClass();

    $card_data = getCardsForCustomer($personal_data->GGUID);

    if(!is_array($card_data) && property_exists($card_data, 'errorMessage')) {
        Log::error("no card_data for " . $personal_data->GGUID . ": " . print_r($card_data, true));
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support', 'unknown_error', 500 );
    }

    $cards = array();
    if(count($card_data) > 0) {
        foreach ($card_data as $card) {
            if (is_object($card) && property_exists($card, 'KVWKARTENNUMMER')) {
                $temp = new stdClass();
                $temp->cardId = $card->KVWKARTENNUMMER;
                $temp->isIndividualCard = property_exists($card, 'KVWISTINDIVIDUELLEKARTE') ? $card->KVWISTINDIVIDUELLEKARTE : false;
                if($temp->isIndividualCard == true && (property_exists($card, 'KVWMODUL') && contains(strtolower('MitarbeiterCARD'), strtolower($card->KVWMODUL)))) {
                    $employer = getEmployerForCardGGUID($card->GGUID);
                    if(isError($employer)) {
                        Log::error('Error in _handleCustomerLogin: Es konnte wahrscheinlich kein Arbeitgeber-Datensatz für die Karte ' . $card->GGUID . ' gefunden werden.');
                        $temp->mitarbeitercardLogo = '';
                    } else {
                        if(!empty($employer) && is_object($employer) && property_exists($employer, 'GGUID')) {
                            $temp->mitarbeitercardLogo = 'https://backend.mycity.cards/api/v1/partners/' . $employer->GGUID . '/logo.png';
                        } else {
                            $temp->mitarbeitercardLogo = '';
                        }
                    }
                }
                array_push($cards, $temp);
            }
        }
    }

    if(App::environment(['development'])) {
        $white_label_website_url = "https://wl-test.trolleymaker.com/";
    }

    $sessionDataToInsert = [
        'id' => $session_token,
        'contact_person_gguid' => $personal_data->GGUID,
        'email' => $email,
        'card_id' => implode(',', array_column($card_data, 'KVWKARTENNUMMER')),
        'user_role' => $user_role,
        'region_name' => $personal_data->NCREGION,
        'card_name' => $personal_data->NCORTDERANMELDUNG,
        'white_label_website_url' => $white_label_website_url,
        'created_at' => Carbon::now()
    ];

    if($loginFromAPI) {
        $jwt = _generateJWT($session_token);
        $sessionDataToInsert['jwt'] = $jwt;
        $returnObject->jwt = $jwt;
    } else {
        $secure_cookie = true;
        if($request->getHost() == 'localhost') {
            $secure_cookie = false;
        }
        $returnObject->secure_cookie = $secure_cookie;
        $returnObject->session_token = $session_token;
    }

    DB::table('mycitycards_sessions')->insert($sessionDataToInsert);
    $returnObject->cards = $cards;
    $returnObject->region = $personal_data->NCREGION;
    $returnObject->cardName = $personal_data->NCORTDERANMELDUNG;
    $returnObject->gguid = $personal_data->GGUID;
    $returnObject->role = $user_role;

    return $returnObject;
}


function _processCustomerLogin($cardIDOrEmail, $password, $email, $addressGGUID) {

    $usernameLinkExists = _checkIfUsernameLinkExists($addressGGUID);
    if(isError($usernameLinkExists)) {
        return $usernameLinkExists;
    }

    if(property_exists($usernameLinkExists, 'GGUID')) {
        //username link exists
        $passwordCheck = _checkPasswordForUsernameGGUID($usernameLinkExists->GGUID, $password);
        if(isError($passwordCheck)) {
            return $passwordCheck;
        }
        if($passwordCheck == false) {
            return createErrorObject('Ungültige Zugangsdaten.', 'invalid_loginData', 400 );
        }
    } else {
        $valueMasterResponse = Http::withHeaders([
            'provider' => 'trolleymaker',
            'password' => 'poiJJ#9q9'
        ])->post('https://valuemaster.brain-behind.com/CU_WebAPI.asmx/CU_Login_User', [
            'CardID' =>  $cardIDOrEmail,
            'Password' => $password,
        ]);

        Log::debug("response: " . print_r($valueMasterResponse->body(), true));

        if($valueMasterResponse && $valueMasterResponse != NULL) {
            if($valueMasterResponse['d'] ){
                $data = json_decode($valueMasterResponse)->d;

                if($data->error && $data->error != '') {
                    if($data->error == 'No User') {
                        return createErrorObject('Die Zugangsdaten sind ungültig.', 'invalid_loginData', 400 );
                    }
                    Log::error("error: " . print_r($valueMasterResponse, true));
                }

                if($data->RoleID == '1' && ($data->CardID == NULL || empty($data->CardID))) {
                    Log::error('Keine Kartennummer nach Login enthalten für ' . $cardIDOrEmail);
                    return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support', 'unknown_error', 500 );
                }

                if($data->RoleID == '1') {

                } else if($data->RoleID == '2') {
                    return createErrorObject('Ihre E-Mail-Adresse wurde als Partner/Interessent oder Arbeitgeber und nicht als Kunde registriert. Bitte melden Sie sich im Partnerportal unter /partner-login oder /arbeitgeber-login an.', 'isntCustomer_loginData' , 400 );
                } else {
                    Log::error('Keine RoleID im Login von ValueMaster zurückbekommen');
                    return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support', 'unknown_error', 500 );
                }

            } else {
                Log::error("error, value master response ungültig: " . $valueMasterResponse->body());
                return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support', 'unknown_error', 500 );
            }
        } else {
            Log::error("error, value master response ungültig: " . $valueMasterResponse->body());
            return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support', 'unknown_error', 500 );
        }

        $now = _getGWNowDate();
        $usernameAndPasswordResponse = createGwUsernameAndPassword($addressGGUID, $email, $password, $now, true);
        if(isError($usernameAndPasswordResponse)) {
            sendErrorNotificationMail('Beim Customer Login konnte für Datensatz ' . $addressGGUID . ' kein Nutzername und Password Link angelegt werden.');
        }
    }
}


Route::post('/partner-login', function (Request $request) {
    $partnerLogin = _handlePartnerLogin($request, false, true);

    if(isError($partnerLogin)) {
        return returnErrorObject($partnerLogin);
    }

    return response()->json( $partnerLogin, 200 )->cookie('X-Authorization', $partnerLogin->session_token, 1440, '/', $request->getHost(), $partnerLogin->secure_cookie, true);
});

function _handlePartnerLogin($request, $generateJWT = false, $checkForNewPortal = false){

    $email = trim($request->input('email'));
    $password = $request->input('password');
    $now = _getGWNowDate();

    if(!$email) {
        return createErrorObject('Es wurde keine E-Mail-Adresse angegeben', 'no_mail', 400 );
    }
    if(!$password) {
        return createErrorObject('Es wurde kein Passwort angegeben', 'no_password', 400 );
    }

    if (str_starts_with($email, '176') || is_numeric($email)) {
        return createErrorObject('Sie versuchen sich gerade mit einer Kartennummer im Partnerportal anzumelden. Falls Sie sich in Ihr CARD-Konto einloggen möchten, nutzen Sie bitte den Kunden-Login.', 'isntPartner_loginData', 400 );
    }



    $personal_data = getGwInterestAndPartnerPersonalData('TMADMINUSER, GWSTYPE, NCINTERESSENTPWD, PRIMARYORGANISATION, TMPARTNERAKTIVIERT, GGUID, NCREGION, NCORTDERANMELDUNG, TMPARTNERPORTALROLLE, NCAKTIV, TMPARTNERINTERESSE, TMMODULEPARTNER, TMARTDERPARTNERSCHAFT, TMISTTESTDATENSATZ', $email, true, false);
    if(!property_exists($personal_data, 'GGUID')) {
        return createErrorObject('Es existiert kein Account mit dieser E-Mail-Adresse.', 'email_unknown', 400 );
    }

    if ($checkForNewPortal) {
        $region = $personal_data->NCREGION;
        $gwsType = $personal_data->GWSTYPE;
        if(in_array($region, config('newRegions.regions_with_new_portal')) && $gwsType!='Interessent'){
            return createErrorObject('Bitte melden Sie sich über unser neues Portal an (https://neu.mycity.cards/partner/login)', 'login_new_portal', 400);
        }
    }

    if(!property_exists($personal_data, 'TMADMINUSER') || $personal_data->TMADMINUSER == '' || strlen($personal_data->TMADMINUSER) <= 0) {
        Log::error("Ansprechpartner hat kein TMADMINUSER bekommen: " . print_r($personal_data, true));
        sendErrorNotificationMail('Ansprechpartner hat kein TMADMINUSER bekommen: ' . $personal_data->GGUID);
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support', 'unknown_error', 500 );
    }
    $email = $personal_data->TMADMINUSER;

    $company_data = getGwPersonalDataByGGUID($personal_data->PRIMARYORGANISATION);
    if(!property_exists($company_data, 'GGUID')) {
        return createErrorObject('Es ist ein Fehler aufgetreten. Die Firma wurde nicht gefunden. Bitte kontaktieren Sie den Support.', 'CompanyNotFound', 400 );
    }

    if(property_exists($personal_data, 'NCAKTIV') && $personal_data->NCAKTIV == false) {
        return createErrorObject('Ihr Account ist deaktiviert. Bitte wenden Sie sich an den Support', 'account_deactivated', 400 );
    }

    if ($personal_data->GWSTYPE === 'Arbeitgeber (MitarbeiterCARD)') {
        return createErrorObject('Sie versuchen sich gerade mit einem Arbeitgeber-Account im Partnerportal anzumelden. Bitte nutzen Sie hierfür den Arbeitgeber-Login unter /arbeitgeber-login', 'isEmployer_loginData', 400 );
    }

    $usernameLinkExists = _checkIfUsernameLinkExists($personal_data->GGUID);
    if(isError($usernameLinkExists)) {
        return $usernameLinkExists;
    }

    $terminalgroupid_gutschein = NULL;
    $terminalgroupid_mitarbeitercard = NULL;

    $getRegionData = Http::withoutVerifying()->withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
    ])->get(config('services.wordpress.regions.endpoint') . '_fields=acf.white_label_website_url,acf.terminalgroupid_gutschein,acf.terminalgroupid_mitarbeitercard&region_name=' . $company_data->NCREGION);

    if($getRegionData->failed()) {
        Log::error($getRegionData->body());
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support', 'unknown_error', 500 );
    }

    $regionData = json_decode($getRegionData);
    if($regionData && count($regionData) > 1) {
        Log::error('Die Region konnte nicht eindeutig zugeordnet werden' . $company_data->NCREGION);
        return createErrorObject('Es ist ein Fehler aufgetreten. Die Region konnte nicht eindeutig zugeordnet werden. Bitte wenden Sie sich an den Support.', 'region_not_unique', 400 );
    }

    $regionData = $regionData[0]->acf;
    $terminalgroupid_gutschein = $regionData->terminalgroupid_gutschein;
    $terminalgroupid_mitarbeitercard = $regionData->terminalgroupid_mitarbeitercard;
    $white_label_website_url = $regionData->white_label_website_url;

    if(!property_exists($personal_data, 'GWSTYPE') || (property_exists($personal_data, 'GWSTYPE') && $personal_data->GWSTYPE != "Interessent")) {

        if(!property_exists($personal_data, 'GWSTYPE') || !property_exists($personal_data, 'TMARTDERPARTNERSCHAFT') || !property_exists($personal_data, 'TMMODULEPARTNER')) {
            Log::error('Der Account für E-Mail Adresse ' . $email . ' hat entweder kein GWSTYPE oder kein TMARTDERPARTNERSCHAFT oder kein TMMODULEPARTNER');
            sendErrorNotificationMail('Der Account für E-Mail Adresse ' . $email . ' hat entweder kein GWSTYPE oder kein TMARTDERPARTNERSCHAFT oder kein TMMODULEPARTNER');
            return createErrorObject('Ihr Account konnte nicht als Partner identifiziert werden. Bitte wenden Sie sich an den Support.', 'unknown_account_type', 400 );
        }

        if(!str_contains($personal_data->TMMODULEPARTNER, 'GutscheinCARD')) {
            return createErrorObject('Ihr Account ist kein Partner-Account.', 'no_partner_account', 400 );
        }

        if(!property_exists($company_data, 'NCFIRMENID')) {
            return createErrorObject('Es ist ein Fehler aufgetreten. Bei Ihrer Firma ist keine FirmenID eingetragen. Bitte kontaktieren Sie den Support.', 'missing_companyID_in_database', 400 );
        }


        if(property_exists($usernameLinkExists, 'GGUID')) {
            //username link exists
            $passwordCheck = _checkPasswordForUsernameGGUID($usernameLinkExists->GGUID, $password);
            if(isError($passwordCheck)) {
                return $passwordCheck;
            }
            if($passwordCheck == false) {
                return createErrorObject('Ungültige Zugangsdaten', 'invalid_loginData', 400 );
            }
        } else {
            //username link does not exist
            $valueMasterResponse = Http::withHeaders([
                'provider' => 'trolleymaker',
                'password' => 'poiJJ#9q9'
            ])->post('https://valuemaster.brain-behind.com/CU_WebAPI.asmx/CU_Login_User', [
                'CardID' =>  $email,
                'Password' => $password,
            ]);

            Log::debug("response: " . print_r($valueMasterResponse->body(), true));

            if($valueMasterResponse && $valueMasterResponse != NULL) {
                if($valueMasterResponse['d'] ) {
                    $data = json_decode($valueMasterResponse)->d;

                    if($data->error && $data->error != '') {
                        if($data->error == 'No User') {
                            return createErrorObject('Ungültige Zugangsdaten', 'invalid_loginData', 400 );
                        }
                        Log::error("error: " . print_r($valueMasterResponse->body(), true));
                    }

                    if($data->RoleID == '2' && ($data->Email == NULL || empty($data->Email))) {
                        Log::error('Keine E-Mail für Role ID = 2 nach Login enthalten');
                        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support', 'unknown_error', 500 );
                    }
                    if($data->RoleID == '1') {
                        return createErrorObject('Ihre E-Mail-Adresse wurde als Kunde und nicht als Partner oder Interessent registriert. Bitte melden Sie sich im Kundenportal an.', 'isntPartner_loginData', 500 );
                    } else if($data->RoleID == '2') {

                    } else {
                        Log::error('Keine RoleID im Login von ValueMaster zurückbekommen');
                        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support', 'unknown_error', 500 );
                    }


                    if(!property_exists($personal_data, 'GWSTYPE') || $personal_data->GWSTYPE == '' || strlen($personal_data->GWSTYPE) <= 0) {
                        Log::error('Kein GWSTYPE von GW');
                        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support', 'unknown_error', 500 );
                    }

                } else {
                    Log::error("error: " . $valueMasterResponse->body());
                    return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500 );
                }
            } else {
                Log::error('Kein ValueMaster response bei Partner Login');
                return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500 );
            }

            $userFieldsToUpdate = new stdClass();
            $userFieldsToUpdate->TMLETZTERLOGIN = $now;

            if(!updateGwAddressData($personal_data->GGUID, $userFieldsToUpdate)) {
                Log::error('Error beim setzen des TMLETZTERLOGIN beim Partner Login: GGUID: ' . $personal_data->GGUID . ', $userFieldsToUpdate: ' . json_encode($userFieldsToUpdate));
                sendErrorNotificationMail('Error beim setzen des TMLETZTERLOGIN beim Partner Login: GGUID: ' . $personal_data->GGUID . ', $userFieldsToUpdate: ' . json_encode($userFieldsToUpdate));
            }

            $usernameAndPasswordResponse = createGwUsernameAndPassword($personal_data->GGUID, $email, $password, $now, true);
            if(isError($usernameAndPasswordResponse)) {
                sendErrorNotificationMail('Beim Partner Login konnte für Datensatz ' . $personal_data->GGUID . ' kein Nutzername und Password Link angelegt werden.');
            }
        }
    } else {
        //Interessenten Login

        if(property_exists($usernameLinkExists, 'GGUID')) {
            //username link exists
            $passwordCheck = _checkPasswordForUsernameGGUID($usernameLinkExists->GGUID, $password);
            if(isError($passwordCheck)) {
                return $passwordCheck;
            }
            if($passwordCheck == false) {
                return createErrorObject('Ungültige Zugangsdaten', 'invalid_loginData', 400 );
            }
        } else {
            //username link does not exist

            if(!property_exists($personal_data, 'NCINTERESSENTPWD') || $personal_data->NCINTERESSENTPWD == NULL || $personal_data->NCINTERESSENTPWD == '') {
                Log::error("Beim Interessenten Login hat der Personal Data Datensatz kein Passwort.");
                sendErrorNotificationMail('Beim Interessenten Login hat der Personal Data Datensatz kein Passwort. GGUID: ' . $personal_data->GGUID);
                return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support', 'unknown_error', 500 );
            }

            if($personal_data->NCINTERESSENTPWD != $password) {
                return createErrorObject('Ungültige Zugangsdaten', 'invalid_loginData', 400 );
            }

            $userFieldsToUpdate = new stdClass();
            $userFieldsToUpdate->TMLETZTERLOGIN = $now;

            if(!updateGwAddressData($personal_data->GGUID, $userFieldsToUpdate)) {
                Log::error('Error beim Setzen des TMLETZTERLOGIN beim Partner Login: GGUID: ' . $personal_data->GGUID . ', $userFieldsToUpdate: ' . json_encode($userFieldsToUpdate));
                sendErrorNotificationMail('Error beim setzen des TMLETZTERLOGIN beim Partner Login: GGUID: ' . $personal_data->GGUID . ', $userFieldsToUpdate: ' . json_encode($userFieldsToUpdate));
            }

            $usernameAndPasswordResponse = createGwUsernameAndPassword($personal_data->GGUID, $email, $password, $now, true);
            if(!isError($usernameAndPasswordResponse)) {
                $clearOldInteressentPWDResponse = updateGwAddressData($personal_data->GGUID, ['NCINTERESSENTPWD' => NULL]);
                if($clearOldInteressentPWDResponse == false) {
                    sendErrorNotificationMail('Für den Datensatz ' . $personal_data->GGUID . ' konnte das NCINTERESSENTPWD nicht gelöscht werden.');
                }
            } else {
                sendErrorNotificationMail('Beim Partner/Interessenten Login konnte für Datensatz ' . $personal_data->GGUID . ' kein Nutzername und Password Link angelegt werden.');
            }
        }

        if(!property_exists($personal_data, 'TMPARTNERINTERESSE') || $personal_data->TMPARTNERINTERESSE == '') {
            $partnerInterestIn = 'Beides';
        } else {
            $partnerInterestIn = $personal_data->TMPARTNERINTERESSE;
        }

        $company_data->NCFIRMENID = NULL;
    }

    if(property_exists($personal_data, 'GWSTYPE') && ($personal_data->GWSTYPE == "Interessent" || $personal_data->GWSTYPE == "Partner" || $personal_data->GWSTYPE == "Partnerschaft")) {
        if($personal_data->TMPARTNERAKTIVIERT == false) {
            return createErrorObject('Ihr Account wurde noch nicht freigeschaltet. Sie werden per E-Mail benachrichtigt, sobald Ihr Account freigeschaltet wurde.', 'account_not_activated_yet', 400 );
        }
    }

    $session_token = (string) Str::uuid();

    $user_role = NULL;
    $partner_user_role = NULL;
    if($personal_data->GWSTYPE == 'Partnerschaft' || $personal_data->GWSTYPE == 'Partner') {
        $user_role = UserRoles::PARTNER;
        if(property_exists($personal_data, 'TMPARTNERPORTALROLLE')) {
            $partner_user_role = getPartnerRolle($personal_data->TMPARTNERPORTALROLLE);
            if($partner_user_role == NULL) {
                Log::error("E-Mail Adresse " . $email . " hat Partner-Login benutzt, aber TMPARTNERPORTALROLLE hat einen unbekannten Wert");
                return createErrorObject('Es ist ein Fehler aufgetreten, die Benutzerrolle ist unbekannt. Bitte wenden Sie sich an den Support.', 'userrole_unknown', 500 );
            }
        } else {
            Log::error("E-Mail Adresse " . $email . " hat Partner-Login benutzt, aber Datensatz hat kein TMPARTNERPORTALROLLE");
            return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500 );
        }

    } else if($personal_data->GWSTYPE == 'Interessent') {
        $user_role = UserRoles::INTERESTED;
    }

    if($user_role == NULL) {
        Log::error("E-Mail Adresse " . $email . " hat Partner-Login benutzt, aber Datensatz hat kein GWSTYPE");
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500 );
    }

    if(!property_exists($company_data, 'NCORTDERANMELDUNG') || $company_data->NCORTDERANMELDUNG == '' || strlen($company_data->NCORTDERANMELDUNG) <= 0) {
        if(property_exists($company_data, 'NCREGION') && $company_data->NCREGION != '') {
            $company_data->NCORTDERANMELDUNG = $company_data->NCREGION . 'CARD';
        } else {
            return createErrorObject('Es ist ein Fehler aufgetreten. Ihr Account konnte keiner Region zugewiesen werden. Bitte wenden Sie sich an den Support.', 'account_has_no_region', 500 );
        }
    }

    $jwt = NULL;
    if($generateJWT == true) {
        $jwt = _generateJWT($session_token);
    }

    if(property_exists($personal_data, 'TMISTTESTDATENSATZ') && $personal_data->TMISTTESTDATENSATZ == true) {
        $terminalgroupid_gutschein = '1212004';
        $terminalgroupid_mitarbeitercard = '1212004';
    }

    if(App::environment(['development'])) {
        $white_label_website_url = "https://wl-test.trolleymaker.com/";
    }


    DB::table('mycitycards_sessions')->insert([
        'id' => $session_token,
        'email' => $email,
        'card_id' => NULL,
        'user_role' => $user_role,
        'partner_user_role' => $partner_user_role,
        'company_gguid' => $company_data->GGUID,
        'contact_person_gguid' => $personal_data->GGUID,
        'terminalgroupid_gutschein' => $terminalgroupid_gutschein,
        'terminalgroupid_mitarbeitercard' => $terminalgroupid_mitarbeitercard,
        'company_id' => $company_data->NCFIRMENID,
        'region_name' => $company_data->NCREGION,
        'card_name' => $company_data->NCORTDERANMELDUNG,
        'white_label_website_url' => $white_label_website_url,
        'jwt' => $jwt,
        'created_at' => Carbon::now(),
        'retain'     => $request->retain ?? FALSE,
    ]);

    $response = new stdClass();

    if($personal_data->GWSTYPE == 'Partner' || $personal_data->GWSTYPE == 'Partnerschaft') {
        $response->partner_role = $partner_user_role;
        $response->partner_data_complete = property_exists($company_data, 'TMPARTNERDATENVOLLSTAENDIG') ? $company_data->TMPARTNERDATENVOLLSTAENDIG : false;
    }

    if($company_data->GWSTYPE == 'Interessent') {
        $response->partner_interest_in = $partnerInterestIn;
    }


    if($generateJWT == true) {
        $response->x_api_token = $jwt;
    } else {
        $secure_cookie = true;
        if($request->getHost() == 'localhost') {
            $secure_cookie = false;
        }
        $response->secure_cookie = $secure_cookie;
        $response->session_token = $session_token;
    }

    $response->region = $company_data->NCREGION;
    $response->cardName = $company_data->NCORTDERANMELDUNG;
    $response->role = $user_role;

    return $response;
}


Route::post('/employer-login', function (Request $request) {

    $employerLogin = _handleEmployerLogin($request);
    if(isError($employerLogin)) {
        return returnErrorObject($employerLogin);
    }

    $secure_cookie = true;
    if($request->getHost() == 'localhost') {
        $secure_cookie = false;
    }

    return response()->json( $employerLogin, 200 )->cookie('X-Authorization', $employerLogin->session_token, 1440, '/', $request->getHost(), $secure_cookie, true);
});


function _handleEmployerLogin($request) {

    $email = trim($request->input('inputEmail'));
    $password = $request->input('password');
    $now = _getGWNowDate();

    if(!$email) {
        return createErrorObject('Es wurde keine E-Mail-Adresse angegeben', 'no_email', 400 );
    }
    if(!$password) {
        return createErrorObject('Es wurde kein Passwort angegeben', 'no_Password', 400 );
    }

    if (str_starts_with($email, '176') || is_numeric($email)) {
        return createErrorObject('Sie versuchen sich gerade mit einer Kartennummer im Partnerportal anzumelden. Falls Sie sich in Ihr CARD-Konto einloggen möchten, nutzen Sie bitte den Kunden-Login.', 'isntEmployer_loginData', 400 );
    }



    $personal_data = getGwInterestAndPartnerPersonalData('TMADMINUSER, GWSTYPE, NCINTERESSENTPWD, PRIMARYORGANISATION, TMPARTNERAKTIVIERT, GGUID, NCREGION, NCORTDERANMELDUNG, TMPARTNERPORTALROLLE, NCAKTIV, TMPARTNERINTERESSE, TMARTDERPARTNERSCHAFT, TMMODULEPARTNER', $email, true, false);
    if(!property_exists($personal_data, 'GGUID')) {
        return createErrorObject('Es existiert kein Account mit dieser E-Mail-Adresse.', 'no_account_found', 400 );
    }

    $company_data = getGwPersonalDataByGGUID($personal_data->PRIMARYORGANISATION);
    if(!property_exists($company_data, 'GGUID')) {
        return createErrorObject('Es ist ein Fehler aufgetreten. Die Firma wurde nicht gefunden. Bitte kontaktieren Sie den Support.', 'no_company_for_user', 500 );
    }

    if(property_exists($personal_data, 'NCAKTIV') && $personal_data->NCAKTIV == false) {
        return createErrorObject('Ihr Account ist deaktiviert. Bitte wenden Sie sich an den Support.', 'account_inactive', 500 );
    }

    if(!property_exists($personal_data, 'GWSTYPE') || $personal_data->GWSTYPE == '' || strlen($personal_data->GWSTYPE) <= 0) {
        Log::error('Kein GWSTYPE von GW, email: ' . $email);
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500 );
    }

    if(property_exists($personal_data, 'GWSTYPE') && ($personal_data->GWSTYPE == "Interessent" || $personal_data->GWSTYPE == "Partnerschaft")) {
        if($personal_data->TMPARTNERAKTIVIERT == false) {
            return createErrorObject('Ihr Account wurde noch nicht freigeschaltet. Sie werden per E-Mail benachrichtigt, sobald Ihr Account freigeschaltet wurde.', 'account_not_yet_permitted', 400 );
        }
    }

    if($personal_data->GWSTYPE != "Interessent" && !property_exists($personal_data, 'TMMODULEPARTNER')) {
        return createErrorObject('Ihr Account ist kein Arbeitgeber-Account.', 'no_employer_account', 400 );
    }

    if($personal_data->GWSTYPE != "Interessent" && !str_contains($personal_data->TMMODULEPARTNER, 'MitarbeiterCARD')) {
        return createErrorObject('Ihr Account ist kein Arbeitgeber-Account.', 'no_employer_account', 400 );
    }

    /*
    if(property_exists($personal_data, 'GWSTYPE') && $personal_data->GWSTYPE === "Partner") {
        return response()->json( [ 'errorMessage' => 'Sie versuchen sich gerade mit einem Partner-Account im Arbeitgeberportal anzumelden. Bitte nutzen Sie hierfür den Partner-Login unter /partner-login' ], 400 );
    }
    */

    if(!property_exists($company_data, 'GWSTYPE') || $company_data->GWSTYPE == '') {
        return createErrorObject('Es ist ein Fehler aufgetreten. Ihrem Account ist kein Portaltyp zugeordnet. Bitte kontaktieren Sie den Support.', 'no_gwstype', 400 );
    }

    $usernameLinkExists = _checkIfUsernameLinkExists($personal_data->GGUID);
    if(isError($usernameLinkExists)) {
        return $usernameLinkExists;
    }

    $user_role = NULL;

    $email = $personal_data->TMADMINUSER;

    if(property_exists($usernameLinkExists, 'GGUID')) {
        //username link exists
        $passwordCheck = _checkPasswordForUsernameGGUID($usernameLinkExists->GGUID, $password);
        if(isError($passwordCheck)) {
            return $passwordCheck;
        }
        if($passwordCheck == false) {
            return createErrorObject('Ungültige Zugangsdaten', 'invalid_loginData', 400 );
        }
    } else {
        if($company_data->GWSTYPE == 'Partnerschaft') {

            $valueMasterResponse = Http::withHeaders([
                'provider' => 'trolleymaker',
                'password' => 'poiJJ#9q9'
            ])->post('https://valuemaster.brain-behind.com/CU_WebAPI.asmx/CU_Login_User', [
                'CardID' =>  $email,
                'Password' => $password,
            ]);

            Log::debug("response: " . print_r($valueMasterResponse->body(), true));

            if($valueMasterResponse && $valueMasterResponse != NULL) {
                if($valueMasterResponse['d'] ) {
                    $data = json_decode($valueMasterResponse)->d;

                    if($data->error && $data->error != '') {
                        if($data->error == 'No User') {
                            return createErrorObject('Ungültige Zugangsdaten', 'invalid_loginData', 400 );
                        }
                        Log::error("error: " . print_r($valueMasterResponse, true));
                    }


                    if($data->RoleID == '2' && ($data->Email == NULL || empty($data->Email))) {
                        Log::error('Keine E-Mail für Role ID = 2 nach Login enthalten');
                        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500 );
                    }

                    if($data->RoleID == '1') {
                        return createErrorObject('Ihre E-Mail-Adresse wurde als Kunde und nicht als Arbeitgeber, Partner oder Interessent registriert. Bitte melden Sie sich im Kundenportal an.', 'unknown_error', 500 );
                    } else if($data->RoleID == '2') {
                        if(!property_exists($personal_data, 'TMADMINUSER') || $personal_data->TMADMINUSER == '' || strlen($personal_data->TMADMINUSER) <= 0) {
                            Log::error("für roleID = 2 keine TMADMINUSER bekommen: " . print_r($personal_data, true));
                            return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500 );
                        }
                    } else {
                        Log::error('Keine RoleID im Login von ValueMaster zurückbekommen');
                        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500 );
                    }

                } else {
                    Log::error("error: " . print_r($valueMasterResponse, true));
                    return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500 );
                }
            } else {
                Log::error('Kein ValueMaster response bei Partner Login');
                return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500 );
            }

            $usernameAndPasswordResponse = createGwUsernameAndPassword($personal_data->GGUID, $email, $password, $now, true);
            if(isError($usernameAndPasswordResponse)) {
                sendErrorNotificationMail('Beim Arbeitgeber Login konnte für Datensatz ' . $personal_data->GGUID . ' kein Nutzername und Password Link angelegt werden.');
            }

        } else if($company_data->GWSTYPE == 'Interessent') {

            if(!property_exists($personal_data, 'NCINTERESSENTPWD')) {
                Log::error("Beim Interessenten Login hat der Personal Data Datensatz kein Passwort.");
                return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500 );
            }

            if($personal_data->NCINTERESSENTPWD != $password) {
                return createErrorObject('Ungültige Zugangsdaten', 'invalid_loginData', 400 );
            }

            $userFieldsToUpdate = new stdClass();
            $userFieldsToUpdate->TMLETZTERLOGIN = $now;

            if(!updateGwAddressData($personal_data->GGUID, $userFieldsToUpdate)) {
                Log::error('Error beim setzen des TMLETZTERLOGIN beim Partner Login: GGUID: ' . $personal_data->GGUID . ', $userFieldsToUpdate: ' . json_encode($userFieldsToUpdate));
                sendErrorNotificationMail('Error beim setzen des TMLETZTERLOGIN beim Arbeitgeber Login: GGUID: ' . $personal_data->GGUID . ', $userFieldsToUpdate: ' . json_encode($userFieldsToUpdate));
            }

            $usernameAndPasswordResponse = createGwUsernameAndPassword($personal_data->GGUID, $email, $password, $now, true);
            if(!isError($usernameAndPasswordResponse)) {
                $clearOldInteressentPWDResponse = updateGwAddressData($personal_data->GGUID, ['NCINTERESSENTPWD' => NULL]);
                if($clearOldInteressentPWDResponse == false) {
                    sendErrorNotificationMail('Für den Datensatz ' . $personal_data->GGUID . ' konnte das NCINTERESSENTPWD nicht gelöscht werden.');
                }
            }
        }
    }

    if($company_data->GWSTYPE == 'Partnerschaft') {
        $user_role = UserRoles::EMPLOYER;

        if(!property_exists($company_data, 'NCFIRMENID')) {
            return createErrorObject('Es ist ein Fehler aufgetreten. Bei Ihrer Firma ist keine FirmenID eingetragen. Bitte kontaktieren Sie den Support.', 'no_firmenid', 400 );
        }
    } else if($company_data->GWSTYPE == 'Interessent') {
        $user_role = UserRoles::INTERESTED;
        $company_data->NCFIRMENID = NULL;
        if(!property_exists($personal_data, 'TMPARTNERINTERESSE') || $personal_data->TMPARTNERINTERESSE == '') {
            $partnerInterestIn = 'Beides';
        } else {
            $partnerInterestIn = $personal_data->TMPARTNERINTERESSE;
        }
    }


    $session_token = (string) Str::uuid();


    $partner_user_role = NULL;
    if(property_exists($personal_data, 'TMPARTNERPORTALROLLE')) {
        $partner_user_role = getPartnerRolle($personal_data->TMPARTNERPORTALROLLE);
        if($partner_user_role == NULL) {
            Log::error("E-Mail Adresse " . $email . " hat Arbeitgeber-Login benutzt, aber TMPARTNERPORTALROLLE hat einen unbekannten Wert");
            return createErrorObject('Es ist ein Fehler aufgetreten, die Benutzerrolle ist unbekannt. Bitte wenden Sie sich an den Support.', 'invalid_partner_user_role', 500 );
        }
    } else {
        Log::error("E-Mail Adresse " . $email . " hat Arbeitgeber-Login benutzt, aber Datensatz hat kein TMPARTNERPORTALROLLE");
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'no_partner_user_role', 500 );
    }

    if(!property_exists($company_data, 'NCORTDERANMELDUNG') || $company_data->NCORTDERANMELDUNG == '' || strlen($company_data->NCORTDERANMELDUNG) <= 0) {
        if(property_exists($company_data, 'NCREGION') && $company_data->NCREGION != '') {
            $company_data->NCORTDERANMELDUNG = $company_data->NCREGION . 'CARD';
        } else {
            $company_data->NCORTDERANMELDUNG = '';
        }
    }

    if($user_role == NULL) {
        Log::error("E-Mail Adresse " . $email . " hat Arbeitgeber-Login benutzt, aber es konnte keine user_role ermittelt werden. GWSTYPE ist weder Partnerschaft noch Interessent");
        return createErrorObject('Es ist ein Fehler aufgetreten, der Accounttyp ist unbekannt. Bitte wenden Sie sich an den Support.', 'invalid_gwstype', 500 );
    }

    DB::table('mycitycards_sessions')->insert([
        'id' => $session_token,
        'email' => $email,
        'card_id' => NULL,
        'user_role' => $user_role,
        'partner_user_role' => $partner_user_role,
        'company_gguid' => $company_data->GGUID,
        'contact_person_gguid' => $personal_data->GGUID,
        'terminalgroupid_gutschein' => NULL,
        'terminalgroupid_mitarbeitercard' => NULL,
        'company_id' => $company_data->NCFIRMENID,
        'region_name' => $company_data->NCREGION,
        'card_name' => $company_data->NCORTDERANMELDUNG,
        'created_at' => Carbon::now()
    ]);

    $response = new stdClass();
    $response->session_token = $session_token;
    $response->region = $company_data->NCREGION;
    $response->cardName = $company_data->NCORTDERANMELDUNG;
    $response->role = $user_role;
    $response->partner_role = $partner_user_role;
    $response->partner_data_complete = property_exists($company_data, 'TMPARTNERDATENVOLLSTAENDIG') ? $company_data->TMPARTNERDATENVOLLSTAENDIG : false;

    if($company_data->GWSTYPE == 'Interessent') {
        $response->partner_interest_in = $partnerInterestIn;
    }

    return $response;
}


Route::post('/contractor-login', function (Request $request) {

    $email = trim($request->input('email'));
    $password = $request->input('password');
    $now = _getGWNowDate();

    if(!$email) {
        return createErrorObject('Es wurde keine E-Mail-Adresse angegeben', 'no_mail', 400 );
    }
    if(!$password) {
        return createErrorObject('Es wurde kein Passwort angegeben', 'no_password', 400 );
    }

    if (str_starts_with($email, '176') || is_numeric($email)) {
        return returnNewErrorObject('Sie versuchen sich gerade mit einer Kartennummer im Auftraggeber-Portal anzumelden. Falls Sie sich in Ihr CARD-Konto einloggen möchten, nutzen Sie bitte den Kunden-Login.', 'isntContractor_loginData', 400 );
    }

    $personal_data = getGwContractorPersonalData('TMADMINUSER, GWSTYPE, PRIMARYORGANISATION, TMPARTNERAKTIVIERT, GGUID, NCREGION, NCORTDERANMELDUNG, TMPARTNERPORTALROLLE, NCAKTIV', $email, true, false);
    if(!property_exists($personal_data, 'GGUID')) {
        return returnNewErrorObject('Es existiert kein Account mit dieser E-Mail-Adresse.', 'email_unknown', 400 );
    }

    if(!property_exists($personal_data, 'TMADMINUSER') || $personal_data->TMADMINUSER == '' || strlen($personal_data->TMADMINUSER) <= 0) {
        Log::error("Ansprechpartner hat kein TMADMINUSER bekommen: " . print_r($personal_data, true));
        sendErrorNotificationMail('Ansprechpartner hat kein TMADMINUSER bekommen: ' . $personal_data->GGUID);
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support', 'unknown_error', 500 );
    }
    $email = $personal_data->TMADMINUSER;

    $company_data = getGwPersonalDataByGGUID($personal_data->PRIMARYORGANISATION);
    if(!property_exists($company_data, 'GGUID')) {
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Die Firma wurde nicht gefunden. Bitte kontaktieren Sie den Support.', 'CompanyNotFound', 400 );
    }

    if(property_exists($personal_data, 'NCAKTIV') && $personal_data->NCAKTIV == false) {
        return returnNewErrorObject('Ihr Account ist deaktiviert. Bitte wenden Sie sich an den Support', 'account_deactivated', 400 );
    }

    $usernameLinkExists = _checkIfUsernameLinkExists($personal_data->GGUID);
    if(isError($usernameLinkExists)) {
        return returnErrorObject($usernameLinkExists);
    }

    $getRegionData = Http::withoutVerifying()->withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
    ])->get(config('services.wordpress.regions.endpoint') . '_fields=acf.white_label_website_url,acf.terminalgroupid_gutschein,acf.terminalgroupid_mitarbeitercard&region_name=' . $personal_data->NCREGION);

    if($getRegionData->failed()) {
        Log::error($getRegionData->body());
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support', 'unknown_error', 500 );
    }

    $regionData = json_decode($getRegionData);
    if($regionData && count($regionData) > 1) {
        Log::error('Die Region konnte nicht eindeutig zugeordnet werden' . $personal_data->NCREGION);
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Die Region konnte nicht eindeutig zugeordnet werden. Bitte wenden Sie sich an den Support.', 'region_not_unique', 400 );
    } else {
        $regionData = $regionData[0]->acf;
        $white_label_website_url = $regionData->white_label_website_url;
    }


    if(property_exists($usernameLinkExists, 'GGUID')) {
        //username link exists
        $passwordCheck = _checkPasswordForUsernameGGUID($usernameLinkExists->GGUID, $password);
        if(isError($passwordCheck)) {
            return $passwordCheck;
        }

        if($passwordCheck == false) {
            return returnNewErrorObject('Ungültige Zugangsdaten', 'invalid_loginData', 400 );
        }

    } else {
        //username link does not exist

        $userFieldsToUpdate = new stdClass();
        $userFieldsToUpdate->TMLETZTERLOGIN = $now;

        if(!updateGwAddressData($personal_data->GGUID, $userFieldsToUpdate)) {
            Log::error('Error beim setzen des TMLETZTERLOGIN beim Partner Login: GGUID: ' . $personal_data->GGUID . ', $userFieldsToUpdate: ' . json_encode($userFieldsToUpdate));
            sendErrorNotificationMail('Error beim setzen des TMLETZTERLOGIN beim Partner Login: GGUID: ' . $personal_data->GGUID . ', $userFieldsToUpdate: ' . json_encode($userFieldsToUpdate));
        }
        $usernameAndPasswordResponse = createGwUsernameAndPassword($personal_data->GGUID, $email, $password, $now, true);
        if(isError($usernameAndPasswordResponse)) {
            sendErrorNotificationMail('Beim Partner Login konnte für Datensatz ' . $personal_data->GGUID . ' kein Nutzername und Password Link angelegt werden.');
        }
    }

    if(!property_exists($personal_data, 'NCORTDERANMELDUNG') || $personal_data->NCORTDERANMELDUNG == '' || strlen($personal_data->NCORTDERANMELDUNG) <= 0) {
        if(property_exists($personal_data, 'NCREGION') && $personal_data->NCREGION != '') {
            $personal_data->NCORTDERANMELDUNG = $personal_data->NCREGION . 'CARD';
        } else {
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Account konnte keiner Region zugewiesen werden. Bitte wenden Sie sich an den Support.', 'account_has_no_region', 500 );
        }
    }

    if(App::environment(['development'])) {
        $white_label_website_url = "https://wl-test.trolleymaker.com/";
    }

    $session_token = (string) Str::uuid();

    DB::table('mycitycards_sessions')->insert([
        'id' => $session_token,
        'email' => $email,
        'card_id' => NULL,
        'user_role' => UserRoles::CONTRACTOR,
        'partner_user_role' => NULL,
        'company_gguid' => $personal_data->PRIMARYORGANISATION,
        'contact_person_gguid' => $personal_data->GGUID,
        'terminalgroupid_gutschein' => NULL,
        'terminalgroupid_mitarbeitercard' => NULL,
        'company_id' => NULL,
        'region_name' => $personal_data->NCREGION,
        'card_name' => $personal_data->NCORTDERANMELDUNG,
        'white_label_website_url' => $white_label_website_url,
        'jwt' => NULL,
        'created_at' => Carbon::now()
    ]);


    $secure_cookie = true;
    if($request->getHost() == 'localhost') {
        $secure_cookie = false;
    }

    $response = new stdClass();
    $response->secure_cookie = $secure_cookie;
    $response->session_token = $session_token;
    $response->region = $personal_data->NCREGION;
    $response->cardName = $personal_data->NCORTDERANMELDUNG;
    $response->role = UserRoles::CONTRACTOR;

    return response()->json( $response, 200 )->cookie('X-Authorization', $response->session_token, 1440, '/', $request->getHost(), $response->secure_cookie, true);
});


Route::post('/trolleymakerportal-login', function (Request $request) {

    $email = trim($request->input('email'));
    $password = $request->input('password');

    if(!$email) {
        return createErrorObject('Es wurde keine E-Mail-Adresse angegeben', 'no_mail', 400 );
    }
    if(!$password) {
        return createErrorObject('Es wurde kein Passwort angegeben', 'no_password', 400 );
    }

    if (str_starts_with($email, '176') || is_numeric($email)) {
        return returnNewErrorObject('Sie versuchen sich gerade mit einer Kartennummer im trolleymaker-Portal anzumelden. Falls Sie sich in Ihr CARD-Konto einloggen möchten, nutzen Sie bitte den Kunden-Login.', 'isntContractor_loginData', 400 );
    }

    $usernameObject = getGwNutzernameForEMail('*', $email);
    if(isError($usernameObject)) {
        return returnErrorObject($usernameObject);
    }
    Log::error(print_r($usernameObject, true));

    if($usernameObject == NULL || !property_exists($usernameObject, 'GGUID')) {
        return returnNewErrorObject('Ungültige Zugangsdaten', 'invalid_credentials', 400 );
    }

    Log::error(print_r($usernameObject, true));

    $passwordLink = getPasswordRecordForUsernameGGUID($usernameObject->GGUID);

    if($passwordLink == NULL || !$passwordLink || count($passwordLink) === 0) {
        Log::error("Fehler in /trolleymakerportal-login beim Abrufen von getPasswordRecordForUsernameGGUID (" . $usernameObject->GGUID . "): Es wurde keine Verknüpfungen gefunden: " . print_r($passwordLink, true));
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
    } else {
        if(count($passwordLink) > 1) {
            Log::error("Fehler in /trolleymakerportal-login beim Abrufen von getPasswordRecordForUsernameGGUID (" . $usernameObject->GGUID . "): Es wurden mehrere Verknüpfungen gefunden: " . print_r($passwordLink, true));
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
        }
    }

    $passwordRecord = $passwordLink[0]->fields;
    if(!property_exists($passwordRecord, 'GGUID')) {
        Log::error("Fehler in /trolleymakerportal-login beim Abrufen von getPasswordRecordForUsernameGGUID (" . $usernameObject->GGUID . "): Objekt hat keine GGUID: " . print_r($passwordRecord, true));
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
    }

    $passwordCheck = _checkPasswordForUsernameGGUID($usernameObject->GGUID, $password);
    if(isError($passwordCheck)) {
        return returnErrorObject($passwordCheck);
    }

    if($passwordCheck == false) {
        return returnNewErrorObject('Ungültige Zugangsdaten', 'invalid_loginData', 400 );
    }


    $personal_data = _getAddressForUsername($usernameObject->GGUID);
    if(isError($personal_data) || $personal_data == NULL) {
        return returnErrorObject($personal_data);
    }
    Log::error('PERSONAL_DATA: ' . print_r($personal_data, true));

    $session_token = (string) Str::uuid();
    $role = UserRoles::TROLLEYMAKER;
    $trolleymaker_role = TrolleymakerUserRoles::USER;
    if(property_exists($personal_data, 'TMTMPORTALROLLE') && $personal_data->TMTMPORTALROLLE != NULL) {
        $lowercased_role = strtolower($personal_data->TMTMPORTALROLLE);
        if($lowercased_role == 'Admin') {
            $trolleymaker_role = TrolleymakerUserRoles::ADMIN;
        } else if($lowercased_role == 'User') {
            $trolleymaker_role = TrolleymakerUserRoles::USER;
        }
    } else {
        Log::error("Fehler in /trolleymakerportal-login: Der Account " . $email . " hat sich versucht anzumelden");
        return returnNewErrorObject('Sie haben nicht die benötigte Berechtigung.', 'forbidden', 403);
    }

    $region = 'trolleymaker';
    $card_name = 'SmartcityCARD & APP';

    DB::table('mycitycards_sessions')->insert([
        'id' => $session_token,
        'email' => $email,
        'card_id' => NULL,
        'user_role' => $role,
        'partner_user_role' => NULL,
        'trolleymaker_user_role' => $trolleymaker_role,
        'company_gguid' => ($personal_data->PRIMARYORGANISATION ?? $personal_data->GGUID),
        'contact_person_gguid' => $personal_data->GGUID,
        'terminalgroupid_gutschein' => NULL,
        'terminalgroupid_mitarbeitercard' => NULL,
        'company_id' => NULL,
        'region_name' => $region,
        'card_name' => $card_name,
        'white_label_website_url' => NULL,
        'jwt' => NULL,
        'created_at' => Carbon::now()
    ]);


    $secure_cookie = true;
    if($request->getHost() == 'localhost') {
        $secure_cookie = false;
    }

    $response = new stdClass();
    $response->secure_cookie = $secure_cookie;
    $response->session_token = $session_token;
    $response->region = $region;
    $response->cardName = $card_name;
    $response->role = $role;

    return response()->json( $response, 200 )->cookie('X-Authorization', $response->session_token, 1440, '/', $request->getHost(), $response->secure_cookie, true);
});


Route::post('/logout', function (Request $request) {

    DB::table('mycitycards_sessions')->where('id', $request->input('session_id'))->delete();

    return response()->json( new stdClass(), 200 )->withoutCookie('X-Authorization');

})->middleware(['AuthenticateWithSession']);

/*
function _getBalanceAmount($cardID) {
    $valueMasterResponse = Http::withHeaders([
        'provider' => 'trolleymaker',
        'password' => 'poiJJ#9q9'
    ])->post('https://valuemaster.brain-behind.com/CU_WebAPI.asmx/CU_Check_Balance_Array', [
        'CardID' =>  $cardID,
        'TerminalID' => '',
    ]);

    $data = json_decode($valueMasterResponse)->d;

    if($data && $data != NULL) {
        if($data->errorMessage && $data->errorMessage != '') {
            return [ 'errorMessage' => 'Fehler: ' . $data->errorMessage ];
        } else {
            $guthaben = 0;
            foreach ($data->CU_Guthaben_Array as $guthaben_object) {
                $guthaben = $guthaben + ($guthaben_object->value / 100);
            }

            return ['balance' => number_format($guthaben, 2, ',', '.')];
        }
    } else {
        return [ 'errorMessage' => 'Es ist ein Fehler aufgetreten.' ];
    }
}
*/

function getBalanceAmountForCardID($cardID) {
    return api_getBalanceAmount($cardID);
}

function getAllBalances($cardID) {
    $valueMasterResponse = Http::withoutVerifying()->withHeaders([
        'provider' => 'trolleymaker',
        'password' => 'poiJJ#9q9'
    ])->post('https://valuemaster.brain-behind.com/CU_WebAPI.asmx/CU_Check_Balance_Array', [
        'CardID' =>  $cardID,
        'TerminalID' => '',
    ]);

    $data = json_decode($valueMasterResponse)->d;

    $balances = [];

    if($data && $data != NULL) {
        if($data->errorMessage && $data->errorMessage != '') {
            return [ 'errorMessage' => 'Fehler: ' . $data->errorMessage ];
        } else {
            //$balances = $data->CU_Guthaben_Array;
            $guthaben = 0;
            foreach ($data->CU_Guthaben_Array as $guthaben_object) {
                $guthaben = $guthaben + $guthaben_object->value;
                $guthaben_object->formattedValue = number_format($guthaben_object->value / 100, 2, ',', '.');
                $balances[$guthaben_object->TerminalgroupID] = $guthaben_object;
            }
            $balances['balanceAmount'] = $guthaben;
            return $balances;
        }
    } else {
        return [ 'errorMessage' => 'Es ist ein Fehler aufgetreten.' ];
    }
}


Route::get('/check-balance', function (Request $request) {

    $balance = getBalanceAmountForCardID($request->input('cardIDs'));

    if(array_key_exists('errorMessage', $balance) && !empty($balance['errorMessage'])) {
        return response()->json( $balance, 500 );
    } else {
        return response()->json( $balance, 200 );
    }
})->middleware(['AuthenticateWithSession', 'AuthenticateIsCustomer']);


Route::get('/transactions', function (Request $request) {

    $cards = getCardsForCustomer($request->input('contact_person_gguid'));
    if(isError($cards) || count($cards) == 0) {
        return returnNewErrorObject('In Ihrem Account sind momentan keine Karten vorhanden.', 'no_cards', 400);
    }
    $cardIDs = array_column($cards, 'KVWKARTENNUMMER');

    $transactions = getCardTransactionsFromGWForMultipleCards($cardIDs, $request->input('amountOfTransactions'));

    if(is_object($transactions) && property_exists($transactions, 'errorMessage')) {
        return response()->json( $transactions, 500 );
    }
    if(is_array($transactions) && array_key_exists('errorMessage', $transactions) && !empty($transactions['errorMessage'])) {
        return response()->json( $transactions, 500 );
    }

    return response()->json( $transactions, 200 );

})->middleware(['AuthenticateWithSession', 'AuthenticateIsCustomer']);


Route::get('/partner-dashboard-transactions', function (Request $request) {
    $transactions = _handleGetPartnerTransactions($request);

    if (isError($transactions)) {
        return returnErrorObject($transactions);
    }
    usort($transactions, function ($a, $b) {
        return $b->date - $a->date;
    });

    $recentTransactions = array_slice($transactions, 0, 3);

    $cardIDs = [];
    foreach ($recentTransactions as $transaction) {
        array_push($cardIDs, $transaction->originalCardID);
    }

    if (count($cardIDs) > 0) {
        $cards_infos = getCardsForCardIDs($cardIDs);

        if (isError($cards_infos)) {
            return returnErrorObject($cards_infos);
        }

        foreach ($transactions as $transaction) {
            foreach ($cards_infos as $card_info) {
                if ($card_info->KVWKARTENNUMMER == $transaction->originalCardID) {
                    $transaction->registered = $card_info->KVWKARTEREGISTRIERT;
                    break;
                }
            }
        }
    }

    $now = Carbon::now()->setTimezone(new DateTimeZone('Europe/Berlin'));

    $todayTransactions = array_filter($transactions, function ($transaction) use ($now) {
        $transactionDate = Carbon::createFromTimestamp($transaction->date)
            ->setTimezone(new DateTimeZone('Europe/Berlin'));
        return $transactionDate->isToday();
    });

    $yesterdayTransactions = array_filter($transactions, function ($transaction) use ($now) {
        $transactionDate = Carbon::createFromTimestamp($transaction->date)
            ->setTimezone(new DateTimeZone('Europe/Berlin'));
        return $transactionDate->isYesterday();
    });

    $weekStart = $now->copy()->startOfWeek();
    $thisWeekTransactions = array_filter($transactions, function ($transaction) use ($weekStart) {
        $transactionDate = Carbon::createFromTimestamp($transaction->date)
            ->setTimezone(new DateTimeZone('Europe/Berlin'));
        return $transactionDate >= $weekStart;
    });

    $lastWeekStart = $now->copy()->subWeek()->startOfWeek();
    $lastWeekTransactions = array_filter($transactions, function ($transaction) use ($lastWeekStart, $weekStart) {
        $transactionDate = Carbon::createFromTimestamp($transaction->date)
            ->setTimezone(new DateTimeZone('Europe/Berlin'));
        return $transactionDate >= $lastWeekStart && $transactionDate < $weekStart;
    });

    $thisMonthStart = $now->copy()->startOfMonth();
    $thisMonthTransactions = array_filter($transactions, function ($transaction) use ($thisMonthStart) {
        $transactionDate = Carbon::createFromTimestamp($transaction->date)
            ->setTimezone(new DateTimeZone('Europe/Berlin'));
        return $transactionDate >= $thisMonthStart;
    });

    $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
    $lastMonthTransactions = array_filter($transactions, function ($transaction) use ($lastMonthStart, $thisMonthStart) {
        $transactionDate = Carbon::createFromTimestamp($transaction->date)
            ->setTimezone(new DateTimeZone('Europe/Berlin'));
        return $transactionDate >= $lastMonthStart && $transactionDate < $thisMonthStart;
    });

    $thisYearStart = $now->copy()->startOfYear();
    $thisYearTransactions = array_filter($transactions, function ($transaction) use ($thisYearStart) {
        $transactionDate = Carbon::createFromTimestamp($transaction->date)
            ->setTimezone(new DateTimeZone('Europe/Berlin'));
        return $transactionDate >= $thisYearStart;
    });

    $lastYearStart = $now->copy()->subYear()->startOfYear();
    $lastYearTransactions = array_filter($transactions, function ($transaction) use ($lastYearStart, $thisYearStart) {
        $transactionDate = Carbon::createFromTimestamp($transaction->date)
            ->setTimezone(new DateTimeZone('Europe/Berlin'));
        return $transactionDate >= $lastYearStart && $transactionDate < $thisYearStart;
    });

    $groupAndCountByTransactionType = function ($transactions) {
        $groupedTransactions = [];

        foreach ($transactions as $transaction) {
            $text = $transaction->text;

            if (!isset($groupedTransactions[$text])) {
                $groupedTransactions[$text] = [];
            }

            $groupedTransactions[$text][] = $transaction;
        }

        return $groupedTransactions;
    };


    $results = [
        'Heute' => $groupAndCountByTransactionType($todayTransactions),
        'Gestern' => $groupAndCountByTransactionType($yesterdayTransactions),
        'diese Woche' => $groupAndCountByTransactionType($thisWeekTransactions),
        'letzte Woche' => $groupAndCountByTransactionType($lastWeekTransactions),
        'dieser Monat' => $groupAndCountByTransactionType($thisMonthTransactions),
        'letzter Monat' => $groupAndCountByTransactionType($lastMonthTransactions),
        'dieses Jahr' => $groupAndCountByTransactionType($thisYearTransactions),
        'letztes Jahr' => $groupAndCountByTransactionType($lastYearTransactions),
    ];

    $countAndFormatTransactions = function ($transactions, $label) {
        $count = count($transactions);
        $sum = 0;

        foreach ($transactions as $transaction) {
            $sum += $transaction->amount;
        }

        $transactionType = ($count > 1) ? 'Transaktionen' : 'Transaktion';
        $message = "$count x $label mit einer gesamten Summe von $sum €";

        return compact('label','count', 'sum', 'transactionType', 'message');
    };

    $transactionLabels = ['Einkauf', 'Kundenbonus', 'Aufladung', 'Einlösung'];

    $formattedResults = [];

    foreach ($results as $timeRange => $groupedTransactions) {
        foreach ($groupedTransactions as $transactionType => $transactions) {
            $formattedResults[$timeRange][$transactionType] = $countAndFormatTransactions($transactions, $transactionType);
        }

        foreach ($transactionLabels as $label) {
            if (!isset($formattedResults[$timeRange][$label])) {
                $formattedResults[$timeRange][$label] = $countAndFormatTransactions([], $label);
            }
        }
    }

    $response = [
        'recentTransactions' => $recentTransactions,
        'formattedResults' => $formattedResults,
    ];

    return response()->json($response, 200);
})->middleware(['AuthenticateWithSession', 'AuthenticateIsPartnerOrEmployer']);

Route::get('/partner-controlling-transactions', function (Request $request) {
    $transactions = _handleGetPartnerTransactions($request);

    if (isError($transactions)) {
        return returnErrorObject($transactions);
    }
    usort($transactions, function ($a, $b) {
        return $b->date - $a->date;
    });

    $recentTransactions = array_slice($transactions, 0, 3);

    $cardIDs = [];
    foreach ($recentTransactions as $transaction) {
        array_push($cardIDs, $transaction->originalCardID);
    }

    if (count($cardIDs) > 0) {
        $cards_infos = getCardsForCardIDs($cardIDs);

        if (isError($cards_infos)) {
            return returnErrorObject($cards_infos);
        }

        foreach ($transactions as $transaction) {
            foreach ($cards_infos as $card_info) {
                if ($card_info->KVWKARTENNUMMER == $transaction->originalCardID) {
                    $transaction->registered = $card_info->KVWKARTEREGISTRIERT;
                    break;
                }
            }
        }
    }

    $now = Carbon::now()->setTimezone(new DateTimeZone('Europe/Berlin'));

    $todayTransactions = array_filter($transactions, function ($transaction) use ($now) {
        $transactionDate = Carbon::createFromTimestamp($transaction->date)
            ->setTimezone(new DateTimeZone('Europe/Berlin'));
        return $transactionDate->isToday();
    });

    $yesterdayTransactions = array_filter($transactions, function ($transaction) use ($now) {
        $transactionDate = Carbon::createFromTimestamp($transaction->date)
            ->setTimezone(new DateTimeZone('Europe/Berlin'));
        return $transactionDate->isYesterday();
    });

    $weekStart = $now->copy()->startOfWeek();
    $thisWeekTransactions = array_filter($transactions, function ($transaction) use ($weekStart) {
        $transactionDate = Carbon::createFromTimestamp($transaction->date)
            ->setTimezone(new DateTimeZone('Europe/Berlin'));
        return $transactionDate >= $weekStart;
    });

    $lastWeekStart = $now->copy()->subWeek()->startOfWeek();
    $lastWeekTransactions = array_filter($transactions, function ($transaction) use ($lastWeekStart, $weekStart) {
        $transactionDate = Carbon::createFromTimestamp($transaction->date)
            ->setTimezone(new DateTimeZone('Europe/Berlin'));
        return $transactionDate >= $lastWeekStart && $transactionDate < $weekStart;
    });

    $thisMonthStart = $now->copy()->startOfMonth();
    $thisMonthTransactions = array_filter($transactions, function ($transaction) use ($thisMonthStart) {
        $transactionDate = Carbon::createFromTimestamp($transaction->date)
            ->setTimezone(new DateTimeZone('Europe/Berlin'));
        return $transactionDate >= $thisMonthStart;
    });

    $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
    $lastMonthTransactions = array_filter($transactions, function ($transaction) use ($lastMonthStart, $thisMonthStart) {
        $transactionDate = Carbon::createFromTimestamp($transaction->date)
            ->setTimezone(new DateTimeZone('Europe/Berlin'));
        return $transactionDate >= $lastMonthStart && $transactionDate < $thisMonthStart;
    });

    $thisYearStart = $now->copy()->startOfYear();
    $thisYearTransactions = array_filter($transactions, function ($transaction) use ($thisYearStart) {
        $transactionDate = Carbon::createFromTimestamp($transaction->date)
            ->setTimezone(new DateTimeZone('Europe/Berlin'));
        return $transactionDate >= $thisYearStart;
    });

    $lastYearStart = $now->copy()->subYear()->startOfYear();
    $lastYearTransactions = array_filter($transactions, function ($transaction) use ($lastYearStart, $thisYearStart) {
        $transactionDate = Carbon::createFromTimestamp($transaction->date)
            ->setTimezone(new DateTimeZone('Europe/Berlin'));
        return $transactionDate >= $lastYearStart && $transactionDate < $thisYearStart;
    });

    $groupAndCountByTransactionType = function ($transactions) {
        $groupedTransactions = [];

        foreach ($transactions as $transaction) {
            $text = $transaction->text;

            if (!isset($groupedTransactions[$text])) {
                $groupedTransactions[$text] = [];
            }

            $groupedTransactions[$text][] = $transaction;
        }

        return $groupedTransactions;
    };


    $results = [
        'Heute' => $groupAndCountByTransactionType($todayTransactions),
        'Gestern' => $groupAndCountByTransactionType($yesterdayTransactions),
        'diese Woche' => $groupAndCountByTransactionType($thisWeekTransactions),
        'letzte Woche' => $groupAndCountByTransactionType($lastWeekTransactions),
        'dieser Monat' => $groupAndCountByTransactionType($thisMonthTransactions),
        'letzter Monat' => $groupAndCountByTransactionType($lastMonthTransactions),
        'dieses Jahr' => $groupAndCountByTransactionType($thisYearTransactions),
        'letztes Jahr' => $groupAndCountByTransactionType($lastYearTransactions),
    ];

    return response()->json($results, 200);
})->middleware(['AuthenticateWithSession', 'AuthenticateIsPartnerOrEmployer']);


Route::get('/partner-transactions', function (Request $request) {
    $transactions = _handleGetPartnerTransactions($request);
    if(isError($transactions)) {
        return returnErrorObject($transactions);
    }

    $cardIDs = [];
    foreach ($transactions as $transaction) {
        array_push($cardIDs, $transaction->originalCardID);
    }

    if(count($cardIDs) > 0) {
        $cards_infos = getCardsForCardIDs($cardIDs);

        if(isError($cards_infos)) {
            return returnErrorObject($transactions);
        }

        foreach ($transactions as $transaction) {
            foreach($cards_infos as $card_infos) {
                if ($card_infos->KVWKARTENNUMMER == $transaction->originalCardID) {
                    $transaction->registered = $card_infos->KVWKARTEREGISTRIERT;
                    break;
                }
            }
            unset($transaction->originalCardID);
        }
    }

    return response()->json( $transactions, 200 );
})->middleware(['AuthenticateWithSession', 'AuthenticateIsPartnerOrEmployer']);


function _handleGetPartnerTransactions($request) {
    $from_date = NULL;
    $to_date = NULL;
    if($request->has('fromDate') && !empty($request->input('fromDate'))) {
        if(validateDateIsISOFormat($request->input('fromDate'))) {
            $from_date = new DateTime($request->input('fromDate'), new DateTimeZone('Europe/Berlin'));
        }
    }
    if($request->has('toDate') && !empty($request->input('toDate'))) {
        if(validateDateIsISOFormat($request->input('toDate'))) {
            $to_date = new DateTime($request->input('toDate'), new DateTimeZone('Europe/Berlin'));
        }
    }
    $censor_cardId = true;
    if(_isEmployer($request)) {
        $censor_cardId = false;
    }
    $transactions = getPartnerTransactionsFromGW($request->input('company_gguid'), $request->input('numberOfTransactions'), $from_date, $to_date, $censor_cardId);
    return $transactions;
}


Route::get('/contractor-dashboard', function (Request $request) {

    $card_name = $request->input('card_name');



    $transactions = getTransactionsFromGWByRegion($card_name);

    if(isError($transactions)) {
        return returnErrorObject($transactions);
    }

    $response = new stdClass();
    $response->currentMonth = new stdClass();
    $response->currentMonth->addVoucher = new stdClass();
    $response->currentMonth->redeemVoucher = new stdClass();
    $response->currentMonth->addBonus = new stdClass();
    $response->currentMonth->purchase = new stdClass();
    $response->currentMonth->addVoucher->sumAmount = 0;
    $response->currentMonth->redeemVoucher->sumAmount = 0;
    $response->currentMonth->addBonus->sumAmount = 0;
    $response->currentMonth->purchase->sumAmount = 0;
    $response->currentMonth->addVoucher->sumEuro = 0;
    $response->currentMonth->redeemVoucher->sumEuro = 0;
    $response->currentMonth->addBonus->sumEuro = 0;
    $response->currentMonth->purchase->sumEuro = 0;
    $response->lastMonth = new stdClass();
    $response->lastMonth->addVoucher = new stdClass();
    $response->lastMonth->redeemVoucher = new stdClass();
    $response->lastMonth->addBonus = new stdClass();
    $response->lastMonth->purchase = new stdClass();
    $response->lastMonth->addVoucher->sumAmount = 0;
    $response->lastMonth->redeemVoucher->sumAmount = 0;
    $response->lastMonth->addBonus->sumAmount = 0;
    $response->lastMonth->purchase->sumAmount = 0;
    $response->lastMonth->addVoucher->sumEuro = 0;
    $response->lastMonth->redeemVoucher->sumEuro = 0;
    $response->lastMonth->addBonus->sumEuro = 0;
    $response->lastMonth->purchase->sumEuro = 0;
    $response->lastThreeMonths = new stdClass();
    $response->lastThreeMonths->addVoucher = new stdClass();
    $response->lastThreeMonths->redeemVoucher = new stdClass();
    $response->lastThreeMonths->addBonus = new stdClass();
    $response->lastThreeMonths->purchase = new stdClass();
    $response->lastThreeMonths->addVoucher->sumAmount = 0;
    $response->lastThreeMonths->redeemVoucher->sumAmount = 0;
    $response->lastThreeMonths->addBonus->sumAmount = 0;
    $response->lastThreeMonths->purchase->sumAmount = 0;
    $response->lastThreeMonths->addVoucher->sumEuro = 0;
    $response->lastThreeMonths->redeemVoucher->sumEuro = 0;
    $response->lastThreeMonths->addBonus->sumEuro = 0;
    $response->lastThreeMonths->purchase->sumEuro = 0;
    $response->amountPartner = 0;
    $response->amountEmployer = 0;
    $response->amountAllCards = 0;

    foreach ($transactions as $transaction) {
        if(property_exists($transaction, 'TADBUCHUNGSART')) {
            $lowercasedBuchungsart = strtolower($transaction->TADBUCHUNGSART);
            if($lowercasedBuchungsart == "disagio" || $lowercasedBuchungsart == "terminalfreischalt" || $lowercasedBuchungsart == "terminalfreischaltung") {
                continue;
            }
        }

        if(property_exists($transaction, 'TMKUNDETXIGNORIEREN') && $transaction->TMKUNDETXIGNORIEREN == true) {
            continue;
        }

        if(property_exists($transaction, 'TMPARTNERTXIGNORIEREN') && $transaction->TMPARTNERTXIGNORIEREN == true) {
            continue;
        }

        if(property_exists($transaction, 'TADBUCHUNGSARTUEBERSETZUNG')) {
            $lowercasedBuchungsart = strtolower($transaction->TADBUCHUNGSARTUEBERSETZUNG);

            $euroPositive = abs($transaction->TADBETRAG);
            $bookingDate = DateTime::createFromFormat('Y-m-d\TH:i:s.vP', $transaction->TADBUCHUNGSDATUM);
            $now = new DateTime('now', new DateTimeZone('Europe/Berlin'));
            $first_day_of_this_month = new DateTime('first day of this month midnight', new DateTimeZone('Europe/Berlin'));
            $first_day_of_last_month = new DateTime('first day of previous month midnight', new DateTimeZone('Europe/Berlin'));
            $last_day_of_last_month = new DateTime('last day of previous month midnight', new DateTimeZone('Europe/Berlin'));
            $first_day_of_last_three_months = new DateTime('first day of -3 month midnight', new DateTimeZone('Europe/Berlin'));
            $last_day_of_last_three_months = new DateTime('last day of -3 month midnight', new DateTimeZone('Europe/Berlin'));

            if($bookingDate >= $first_day_of_this_month && $bookingDate <= $now) {
                switch ($lowercasedBuchungsart) {
                    case 'aufladung':
                        $response->currentMonth->addVoucher->sumAmount++;
                        $response->currentMonth->addVoucher->sumEuro += $euroPositive;
                        break;
                    case 'einlösung':
                        $response->currentMonth->redeemVoucher->sumAmount++;
                        $response->currentMonth->redeemVoucher->sumEuro += $euroPositive;
                        break;
                    case 'kundenbonus':
                        $response->currentMonth->addBonus->sumAmount++;
                        $response->currentMonth->addBonus->sumEuro += $euroPositive;
                        break;
                    case 'einkauf':
                        $response->currentMonth->purchase->sumAmount++;
                        $response->currentMonth->purchase->sumEuro += $euroPositive;
                        break;
                    default:
                        break;
                }
            } else if($bookingDate >= $first_day_of_last_month && $bookingDate <= $last_day_of_last_month) {
                switch ($lowercasedBuchungsart) {
                    case 'aufladung':
                        $response->lastMonth->addVoucher->sumAmount++;
                        $response->lastMonth->addVoucher->sumEuro += $euroPositive;
                        break;
                    case 'einlösung':
                        $response->lastMonth->redeemVoucher->sumAmount++;
                        $response->lastMonth->redeemVoucher->sumEuro += $euroPositive;
                        break;
                    case 'kundenbonus':
                        $response->lastMonth->addBonus->sumAmount++;
                        $response->lastMonth->addBonus->sumEuro += $euroPositive;
                        break;
                    case 'einkauf':
                        $response->lastMonth->purchase->sumAmount++;
                        $response->lastMonth->purchase->sumEuro += $euroPositive;
                        break;
                    default:
                        break;
                }
            } else if($bookingDate >= $first_day_of_last_three_months && $bookingDate <= $last_day_of_last_three_months) {
                switch ($lowercasedBuchungsart) {
                    case 'aufladung':
                        $response->lastThreeMonths->addVoucher->sumAmount++;
                        $response->lastThreeMonths->addVoucher->sumEuro += $euroPositive;
                        break;
                    case 'einlösung':
                        $response->lastThreeMonths->redeemVoucher->sumAmount++;
                        $response->lastThreeMonths->redeemVoucher->sumEuro += $euroPositive;
                        break;
                    case 'kundenbonus':
                        $response->lastThreeMonths->addBonus->sumAmount++;
                        $response->lastThreeMonths->addBonus->sumEuro += $euroPositive;
                        break;
                    case 'einkauf':
                        $response->lastThreeMonths->purchase->sumAmount++;
                        $response->lastThreeMonths->purchase->sumEuro += $euroPositive;
                        break;
                    default:
                        break;
                }
            }
        }
    }

    $response->currentMonth->addVoucher->sumEuro = round($response->currentMonth->addVoucher->sumEuro, 2);
    $response->currentMonth->redeemVoucher->sumEuro = round($response->currentMonth->redeemVoucher->sumEuro, 2);
    $response->currentMonth->addBonus->sumEuro = round($response->currentMonth->addBonus->sumEuro, 2);
    $response->currentMonth->purchase->sumEuro = round($response->currentMonth->purchase->sumEuro, 2);
    $response->lastMonth->addVoucher->sumEuro = round($response->lastMonth->addVoucher->sumEuro, 2);
    $response->lastMonth->redeemVoucher->sumEuro = round($response->lastMonth->redeemVoucher->sumEuro, 2);
    $response->lastMonth->addBonus->sumEuro = round($response->lastMonth->addBonus->sumEuro, 2);
    $response->lastMonth->purchase->sumEuro = round($response->lastMonth->purchase->sumEuro, 2);
    $response->lastThreeMonths->addVoucher->sumEuro = round($response->lastThreeMonths->addVoucher->sumEuro, 2);
    $response->lastThreeMonths->redeemVoucher->sumEuro = round($response->lastThreeMonths->redeemVoucher->sumEuro, 2);
    $response->lastThreeMonths->addBonus->sumEuro = round($response->lastThreeMonths->addBonus->sumEuro, 2);
    $response->lastThreeMonths->purchase->sumEuro = round($response->lastThreeMonths->purchase->sumEuro, 2);

    $gwPartnersResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/query', [
        'query' => 'SELECT GGUID, COMPNAME, GWSTYPE, TMARTDERPARTNERSCHAFT, NCORTDERANMELDUNG, TMISTTESTDATENSATZ, GWISCOMPANY FROM address WHERE ((GWSTYPE="Partnerschaft" AND (TMARTDERPARTNERSCHAFT="Partner" OR TMARTDERPARTNERSCHAFT="Partner und Auftraggeber")) OR GWSTYPE="Partner" OR GWSTYPE="Arbeitgeber (MitarbeiterCARD)") AND NCORTDERANMELDUNG="' . $card_name . '" AND GWISCOMPANY = true AND TMISTTESTDATENSATZ != true'
    ]);

    if($gwPartnersResponse->failed() || count(json_decode($gwPartnersResponse)) <= 0 || !property_exists(json_decode($gwPartnersResponse)[0], 'rows') || count(json_decode($gwPartnersResponse)[0]->rows) <= 0) {
        if($gwPartnersResponse->status() == 503) {
            return returnNewErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error('contractor-dashboard: \n\n' . $gwPartnersResponse->body());
            return returnNewErrorObject('Es ist ein unbekannter Fehler aufgetreten.', 'unknown_error', 500);
        }
    }

    $gwPartnerData = json_decode($gwPartnersResponse)[0]->rows;

    if(count($gwPartnerData) > 0) {
        foreach($gwPartnerData as $partner) {
            if($partner->GWSTYPE == 'Partner' || ($partner->GWSTYPE == 'Partnerschaft' && ($partner->TMARTDERPARTNERSCHAFT == 'Partner' || $partner->TMARTDERPARTNERSCHAFT == 'Partner und Auftraggeber'))) {
                $response->amountPartner++;
            } else if($partner->GWSTYPE == 'Arbeitgeber (MitarbeiterCARD)') {
                $response->amountEmployer++;
            }
        }
    }



    $gwCardsResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/query', [
        'query' => 'SELECT COUNT(GGUID) AS amount_cards FROM kartenverwaltung WHERE KVWORTDERANMELDUNG="' . $card_name . '" AND KVWISTTESTKARTE != true'
    ]);

    if($gwCardsResponse->failed() || count(json_decode($gwCardsResponse)) <= 0 || !property_exists(json_decode($gwCardsResponse)[0], 'rows') || count(json_decode($gwCardsResponse)[0]->rows) <= 0) {
        if($gwCardsResponse->status() == 503) {
            return returnNewErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error('contractor-dashboard: \n\n' . $gwCardsResponse->body());
            return returnNewErrorObject('Es ist ein unbekannter Fehler aufgetreten.', 'unknown_error', 500);
        }
    }

    $gwCardsData = json_decode($gwCardsResponse)[0]->rows;

    Log::error('cards: ' . print_r($gwCardsData, true));

    if(count($gwCardsData) > 0) {
        $response->amountAllCards = $gwCardsData[0]->AMOUNT_CARDS;
    }


    return response()->json( $response, 200 );
})->middleware(['AuthenticateWithSession', 'AuthenticateIsContractor']);


function getCardsAndAddressForEmployer($employerAddressGguid) {

    $gwCardsResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/query', [
        "query" => "SELECT a.GGUID as AGGUID, a.NAME, a.CHRISTIANNAME, a.TMNOTES3, a.GWPERSONNELNUMBER, a.TMCHRISTIANNAMEAG, a.TMNAMEAG, kvw.* FROM address a LINK_JOIN(linkattribute='TMKVWADRESSE') kartenverwaltung kvw WHERE kvw.IsLinkedToWhere(address p:WHERE p.GGUID = 0x" . $employerAddressGguid . ";LinkAttribute='TMKARTEARBEITGE')"
    ]);

    if($gwCardsResponse->failed()) {
        if($gwCardsResponse->status() == 503) {
            return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error('getCardsAndAddressForEmployer: \n\n' . $gwCardsResponse->body());
            return createErrorObject('Es ist ein unbekannter Fehler aufgetreten.', 'unknown_error', 500);
        }
    }

    if(count(json_decode($gwCardsResponse)) == 0) {
        return [];
    }

    $gwAddressData = json_decode($gwCardsResponse)[0]->rows;
    return $gwAddressData;
}

Route::get('/employer-cards', function (Request $request) {

    $employer_cards = getCardsAndAddressForEmployer($request->input('company_gguid'));

    if(isError($employer_cards)) {
        return returnErrorObject($employer_cards);
    }

    $response_array = [];
    foreach ($employer_cards as $card) {
        $cardObject = new stdClass();
        $cardObject->cardID = $card->KVWKARTENNUMMER;
        $cardObject->isChargingActive = $card->KVWBELADUNGAKTIV;
        $cardObject->chargeFrequence = property_exists($card, 'KVWLADERYTHMUS') ? $card->KVWLADERYTHMUS : '';
        $cardObject->chargeAmount = property_exists($card, 'KVWLADEBETRAG') ? number_format($card->KVWLADEBETRAG, 2, ',', '.') : '';
        $cardObject->chargeAmountOriginal = property_exists($card, 'KVWLADEBETRAG') ? $card->KVWLADEBETRAG : '';
        $cardObject->chargeTime = property_exists($card, 'KVWLADEZEITPUNKT') ? $card->KVWLADEZEITPUNKT : '';
        if(property_exists($card, 'KVWNEXTLADETERMIN') && $card->KVWNEXTLADETERMIN != NULL && !empty($card->KVWNEXTLADETERMIN)) {
            $cardObject->nextChargeTimestamp = gWDateToGermanDate($card->KVWNEXTLADETERMIN);
        } else {
            $cardObject->nextChargeTimestamp = '';
        }
        $cardObject->isCardActive = property_exists($card, 'KVWKARTEAKTIVVM') ? $card->KVWKARTEAKTIVVM : false;
        $cardObject->isFlaggedForDeletion = property_exists($card, 'KVWLOESCHANFORDERUNG') ? $card->KVWLOESCHANFORDERUNG : false;
        $cardObject->deletionDate = property_exists($card, 'KVWLOESCHDATUM') ? $card->KVWLOESCHDATUM : '';
        $cardObject->isCardRegistered = property_exists($card, 'KVWKARTEREGISTRIERT') ? $card->KVWKARTEREGISTRIERT : false;

        if($cardObject->isCardRegistered) {
            $cardObject->firstName = property_exists($card, 'CHRISTIANNAME') ? $card->CHRISTIANNAME : '';
            $cardObject->lastName = property_exists($card, 'NAME') ? $card->NAME : '';
        } else {
            $cardObject->firstName = property_exists($card, 'TMCHRISTIANNAMEAG') ? $card->TMCHRISTIANNAMEAG : '';
            $cardObject->lastName = property_exists($card, 'TMNAMEAG') ? $card->TMNAMEAG : '';
        }

        $cardObject->personnelNote = property_exists($card, 'TMNOTES3') ? $card->TMNOTES3 : '';
        $cardObject->personnelNumber = property_exists($card, 'GWPERSONNELNUMBER') ? $card->GWPERSONNELNUMBER : '';

        array_push($response_array, $cardObject);
    }

    return response()->json( $response_array, 200 );


})->middleware(['AuthenticateWithSession']);



Route::post('/update-employer-card', function (Request $request) {

    $requestCardID = $request->input('cardID');
    if(!$request->has('cardID') || $request->input('cardID') == NULL || $request->input('cardID') == '') {
        Log::error('Fehler bei /update-employer-card, es wurden keine cardID mitgeschickt!');
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'no_cardID', 400);
    }

    if(!isValidCardIDSyntax($request->input('cardID'))) {
        Log::error("Fehler bei /update-employer-card, es wurden keine ungültige cardID mitgeschickt! : " . $request->input('cardID'));
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'invalid_cardID', 400);
    }

    if(!$request->has('firstName') || $request->input('firstName') == NULL || $request->input('firstName') == '') {
        return returnNewErrorObject('Das Feld für den Vornamen ist ein Pflichtfeld.', 'no_firstName', 400);
    }
    if(!$request->has('lastName') || $request->input('lastName') == NULL || $request->input('lastName') == '') {
        return returnNewErrorObject('Das Feld für den Nachnamen ist ein Pflichtfeld.', 'no_lastName', 400);
    }
    if(!$request->has('personnelNumber') || $request->input('personnelNumber') == NULL || $request->input('personnelNumber') == '') {
        return returnNewErrorObject('Das Feld für die Personalnummer ist ein Pflichtfeld.', 'no_personnelNumber', 400);
    }

    $employer_cards = getCardsForEmployer($request->input('company_gguid'));

    if(isError($employer_cards)) {
        return returnErrorObject($employer_cards);
    }

    $cardFromGW = new stdClass();
    $cardFound = false;
    foreach ($employer_cards as $employerCard) {
        if(intval($employerCard->KVWKARTENNUMMER) === intval($requestCardID)) {
            $cardFound = true;
            $cardFromGW = $employerCard;
        }
    }
    if($cardFound == false) {
        Log::error('Fehler bei /update-employer-card, die CardID ' . $requestCardID . ' gehört nicht zu dem Unternehmen.');
        return returnNewErrorObject('Die Kartennummer ' . $requestCardID . ' ist nicht Ihrem Unternehmen zugewiesen. Bitte wenden Sie sich an den Support.', 'invalid_cardIDs', 400);
    }


    $customer = getCustomerForCardGGUID($cardFromGW->GGUID);

    if(isError($customer)) {
        return returnErrorObject($customer);
    }

    $userFieldsToUpdate = new stdClass();
    $userFieldsToUpdate->TMNOTES3 = $request->input('personnelNote');
    $cardFieldsToUpdate = new stdClass();
    $cardFieldsToUpdate->KVWBELADUNGAKTIV = $request->input('isChargingActive');

    if($request->has('isFlaggedForDeletion') && $request->input('isFlaggedForDeletion') != NULL && $request->input('isFlaggedForDeletion') == true) {
        if($request->has('deletionDate') && $request->input('deletionDate') != NULL && $request->input('deletionDate') !== '') {
            $cardFieldsToUpdate->KVWLOESCHANFORDERUNG = true;
            $cardFieldsToUpdate->KVWLOESCHDATUM = htmlDateToGwDate($request->input('deletionDate'));
            $userFieldsToUpdate->TMNOTES3 .= ' - Mitarbeiter scheidet aus.';
        } else {
            return returnNewErrorObject('Wenn der Mitarbeiter/CARD das Unternehmen verlässt muss ein Austrittsdatum angegeben werden.', 'no_deletionDate', 400);
        }
    } else {
        $cardFieldsToUpdate->KVWLOESCHANFORDERUNG = false;
        $cardFieldsToUpdate->KVWLOESCHDATUM = NULL;
    }

    if($request->has('chargeFrequence') && $request->input('chargeFrequence') != NULL && $request->input('chargeFrequence') != '') {
        $possibleChargeFrequenceValues = ['Monat', 'Quartal', 'Halbjahr', 'Jahr', 'unregelmäßig'];
        if(!in_array($request->input('chargeFrequence'), $possibleChargeFrequenceValues)) {
            return returnNewErrorObject('Ungültige Ladefrequenz.', 'invalid_chargeFrequence', 400);
        }
        $cardFieldsToUpdate->KVWLADERYTHMUS = htmlspecialchars($request->input('chargeFrequence'));
    } else {
        $cardFieldsToUpdate->KVWLADERYTHMUS = htmlspecialchars($request->input('chargeFrequence'));
    }

    if($request->has('chargeTime') && $request->input('chargeTime') != NULL && $request->input('chargeTime') != '') {
        $possibleChargeTimeValues = ['Anfang des Monats', 'Mitte des Monats', 'Ende des Monats', 'Individuell'];
        if(!in_array($request->input('chargeTime'), $possibleChargeTimeValues)) {
            return returnNewErrorObject('Ungültiger Ladezeitpunkt.', 'invalid_chargeTime', 400);
        }
        $cardFieldsToUpdate->KVWLADEZEITPUNKT = htmlspecialchars($request->input('chargeTime'));

        if($request->input('chargeTime') == 'Individuell') {
            if($request->has('customChargeTimeDate') && $request->input('customChargeTimeDate') != NULL && $request->input('customChargeTimeDate') != '') {
                $cardFieldsToUpdate->KVWBELADETERMIN = htmlDateToGwDate($request->input('customChargeTimeDate'));
            } else {
                return returnNewErrorObject('Bei Ladezeitpunkt = individuell muss ein individueller Ladezeitpunkt als Datum angegeben werden.', 'no_customChargeTimeDate', 400);
            }
        }
    } else {
        $cardFieldsToUpdate->KVWLADEZEITPUNKT = htmlspecialchars($request->input('chargeTime'));
    }

    if($request->has('chargeAmount') && $request->input('chargeAmount') != NULL && $request->input('chargeAmount') != '') {
        if(str_contains($request->input('chargeAmount'), '.')) {
            return returnNewErrorObject('Ungültiger Ladebetrag. Bitte wenden Sie sich an den Support.', 'invalid_chargeAmount', 400);
        }

        $chargeAmount = $request->input('chargeAmount');
        $chargeAmountCent = (int) getAmountCentForBetragInput($chargeAmount);

        if(!_isValidAmountCent($chargeAmountCent)) {
            return returnNewErrorObject('Ungültiger Aufladungsbetrag.', 'invalid_amount_cent', 400);
        }

        if($chargeAmountCent > 25000) {
            return returnNewErrorObject('Es dürfen nur maximal 250€ aufgeladen werden.', 'max_250_euro_charge', 400);
        }

        $cardFieldsToUpdate->KVWLADEBETRAG = $chargeAmount;
    } else {
        $cardFieldsToUpdate->KVWLADEBETRAG = $request->input('chargeAmount');
    }

    $cardFieldsToUpdate->KVWLADEZEITPUNKT = $request->input('chargeTime');
    if(!updateGwCardData($cardFromGW->GGUID, $cardFieldsToUpdate)) {
        Log::error("update-employer-cards: failed updating CARD: " . $cardFromGW->GGUID . " Fields: " . print_r($cardFieldsToUpdate, true));
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 400);
    }

    if(!property_exists($cardFromGW, 'KVWKARTEREGISTRIERT') || $cardFromGW->KVWKARTEREGISTRIERT === false) {
        $userFieldsToUpdate->CHRISTIANNAME = $request->input('firstName');
        $userFieldsToUpdate->NAME = $request->input('lastName');
    }

    $userFieldsToUpdate->GWPERSONNELNUMBER = $request->input('personnelNumber');
    if(!updateGwAddressData($customer->GGUID, $userFieldsToUpdate)) {
        Log::error("update-employer-cards: failed updating USER: " . $cardFromGW->GGUID . " Fields: " . print_r($userFieldsToUpdate, true));
        return returnNewErrorObject('Es ist ein Fehler aufgetreten, die Karten wurden aktualisiert, aber der Nutzer nicht. Bitte kontaktieren Sie den Support.', 'unknown_error', 400);
    }

    return response()->json( "" , 200 );

})->middleware(['AuthenticateWithSession']);



Route::post('/update-multi-employer-cards', function (Request $request) {

    if(!$request->has('cardIDsToUpdate') || $request->input('cardIDsToUpdate') == NULL || $request->input('cardIDsToUpdate') == '' || !is_array($request->input('cardIDsToUpdate'))) {
        Log::error('Fehler bei /update-multi-employer-cards, es wurden keine CardIDs mitgeschickt!');
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'no_cardIDs', 400);
    }

    if(!$request->has('fieldsToUpdate') || $request->input('fieldsToUpdate') == NULL || $request->input('fieldsToUpdate') == '') {
        Log::error('Fehler bei /update-multi-employer-cards, es wurden keine fieldsToUpdate mitgeschickt!');
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'no_fieldsToUpdate', 400);
    }

    $employer_cards = getCardsForEmployer($request->input('company_gguid'));

    if(isError($employer_cards)) {
        return returnErrorObject($employer_cards);
    }

    $requestCardIDs = $request->input('cardIDsToUpdate');
    $cardsToUpdate = [];
    foreach ($requestCardIDs as $requestCardID) {
        $cardFound = false;
        foreach ($employer_cards as $employerCard) {
            if(intval($employerCard->KVWKARTENNUMMER) === intval($requestCardID)) {
                $cardFound = true;
                $cardToUpdate = new stdClass();
                $cardToUpdate->cardID = $requestCardID;
                $cardToUpdate->gguid = $employerCard->GGUID;
                $cardsToUpdate[] = $cardToUpdate;
            }
        }
        if($cardFound == false) {
            return returnNewErrorObject('Die Kartennummer ' . $requestCardID . ' ist nicht Ihrem Unternehmen zugewiesen. Bitte wenden Sie sich an den Support.', 'invalid_cardIDs', 400);
        }
    }

    $fieldsToUpdate = new stdClass();

    if($request->has('fieldsToUpdate.isChargingActive') && $request->input('fieldsToUpdate.isChargingActive') != NULL && $request->input('fieldsToUpdate.isChargingActive') != '') {
        $isChargingActive = $request->input('fieldsToUpdate.isChargingActive');
        if(!is_bool($request->input('fieldsToUpdate.isChargingActive'))) {
            if(is_string($request->input('fieldsToUpdate.isChargingActive'))) {
                if($request->input('fieldsToUpdate.isChargingActive') === 'true') {
                    $isChargingActive = true;
                } else if($request->input('fieldsToUpdate.isChargingActive') === 'false') {
                    $isChargingActive = false;
                } else {
                    return returnNewErrorObject('Ungültiger Wert für "Beladung aktiv?". Bitte wenden Sie sich an den Support.', 'invalid_isChargingActive', 400);
                }
            } else {
                return returnNewErrorObject('Ungültiger Wert für "Beladung aktiv?". Bitte wenden Sie sich an den Support.', 'invalid_isChargingActive', 400);
            }
        }
        $fieldsToUpdate->KVWBELADUNGAKTIV = $isChargingActive;
    }

    if($request->has('fieldsToUpdate.chargeFrequence') && $request->input('fieldsToUpdate.chargeFrequence') != NULL && $request->input('fieldsToUpdate.chargeFrequence') != '') {
        $possibleChargeFrequenceValues = ['Monat', 'Quartal', 'Halbjahr', 'Jahr', 'unregelmäßig'];
        if(!in_array($request->input('fieldsToUpdate.chargeFrequence'), $possibleChargeFrequenceValues)) {
            return returnNewErrorObject('Ungültige Ladefrequenz. Bitte wenden Sie sich an den Support.', 'invalid_chargeFrequence', 400);
        }
        $fieldsToUpdate->KVWLADERYTHMUS = htmlspecialchars($request->input('fieldsToUpdate.chargeFrequence'));
    }

    if($request->has('fieldsToUpdate.chargeTime') && $request->input('fieldsToUpdate.chargeTime') != NULL && $request->input('fieldsToUpdate.chargeTime') != '') {
        $possibleChargeTimeValues = ['Anfang des Monats', 'Mitte des Monats', 'Ende des Monats', 'Individuell'];
        if(!in_array($request->input('fieldsToUpdate.chargeTime'), $possibleChargeTimeValues)) {
            return returnNewErrorObject('Ungültiger Ladezeitpunkt. Bitte wenden Sie sich an den Support.', 'invalid_chargeTime', 400);
        }
        $fieldsToUpdate->KVWLADEZEITPUNKT = htmlspecialchars($request->input('fieldsToUpdate.chargeTime'));

        if($request->input('fieldsToUpdate.chargeTime') == 'Individuell') {
            if($request->has('fieldsToUpdate.customChargeTimeDate') && $request->input('fieldsToUpdate.customChargeTimeDate') != NULL && $request->input('fieldsToUpdate.customChargeTimeDate') != '') {
                $fieldsToUpdate->KVWBELADETERMIN = htmlDateToGwDate($request->input('fieldsToUpdate.customChargeTimeDate'));
            } else {
                return returnNewErrorObject('Bei Ladezeitpunkt = individuell muss ein individueller Ladezeitpunkt als Datum angegeben werden.', 'no_customChargeTimeDate', 400);
            }
        }
    }

    if($request->has('fieldsToUpdate.chargeAmount') && $request->input('fieldsToUpdate.chargeAmount') != NULL && $request->input('fieldsToUpdate.chargeAmount') != '') {

        if(!is_numeric($request->input('fieldsToUpdate.chargeAmount')) && !str_contains($request->input('fieldsToUpdate.chargeAmount'), ',')) {
            return returnNewErrorObject('Ungültiger Ladebetrag. Bitte wenden Sie sich an den Support.', 'invalid_chargeAmount', 400);
        }

        $chargeAmount = $request->input('fieldsToUpdate.chargeAmount');
        if(str_contains($chargeAmount, '.')) {
            $chargeAmount = number_format($chargeAmount, 2, ',', '');
        }

        if($chargeAmount > 250) {
            return returnNewErrorObject('Es dürfen nur maximal 250€ aufgeladen werden.', 'max_250_euro_charge', 400);
        }

        $fieldsToUpdate->KVWLADEBETRAG = $chargeAmount;
    }


    foreach ($cardsToUpdate as $cardToUpdate) {
        if(!updateGwCardData($cardToUpdate->gguid, $fieldsToUpdate)) {
            Log::error("update-multi-employer-cards: failed updating object: " . $cardToUpdate->gguid . " Fields: " . print_r($fieldsToUpdate, true));
        }
    }

    return response()->json( $request, 200 );

})->middleware(['AuthenticateWithSession']);


Route::get('/region-data', function (Request $request) {

    $validator = Validator::make([
        'fields' => $request->input('fields')
    ], [
        'fields' => 'required|regex:/[\pL\-\,\_\.]+$/'
    ]);

    if ($validator->fails()) {
        Log::error('Bei /region-data wurden keine fields angegeben.');
        return response()->json( new stdClass(), 200 );
    }

    $fields = explode(',', htmlspecialchars($request->input('fields')));

    $regionData = getRegionData($request->input('region_name'), $request->input('card_name'), $fields);
    if(isError($regionData) || !property_exists($regionData, 'acf')) {
        Log::error('Fehler bei /region-data, die Regionsdaten konnte nicht abgerufen werden: ' . print_r($regionData));
        sendErrorNotificationMail('Fehler bei /region-data, die Regionsdaten konnte nicht abgerufen werden: ' . print_r($regionData));
        return returnNewErrorObject('Es ist ein Fehler bei aufgetreten, die Regionsdaten konnten nicht abgerufen werden. Wenn das Problem weiterhin besteht, kontaktieren Sie bitte den Support.', 'unknown_error', 500);
    }

    $regionData = $regionData->acf;

    if(in_array('acf.image_region_header', $fields) && !property_exists($regionData, 'image_region_header') || empty($regionData->image_region_header)) {
        $regionData->image_region_header = 'https://regionen.trolleymaker.com/wp-content/uploads/frau-mit-einkaufstaschen.jpg';
    }
    if(in_array('acf.image_region_contact_person', $fields) && !property_exists($regionData, 'image_region_contact_person') || empty($regionData->image_region_contact_person)) {
        $regionData->image_region_contact_person = 'https://regionen.trolleymaker.com/wp-content/uploads/frau-mit-einkaufstaschen.jpg';
    }

    return response()->json( $regionData, 200 );

})->middleware(['AuthenticateWithSession']);


Route::get('/dashboard', function (Request $request) {

    $cardID = $request->query('cardID');
    $transactions_and_balance = _getCustomersTransactionsAndBalance($request, $cardID);
    if(isError($transactions_and_balance)) {
        return returnErrorObject($transactions_and_balance);
    }

    $perks = _getPerksForAddress($request->input('contact_person_gguid'));
    if(isError($perks)) {
        return returnErrorObject($perks);
    }

    $response = array();
    $response['cardsData'] = $transactions_and_balance;
    $response['perks'] = $perks;

    return response()->json($response, 200);
})->middleware(['AuthenticateWithSession']);


function _getCustomersTransactionsAndBalance($request) {
    $cards = getCardsForCustomer($request->input('contact_person_gguid'));
    if(isError($cards) || count($cards) == 0) {
        return createErrorObject('In Ihrem Account sind momentan keine Karten vorhanden.', 'no_cards', 400);
    }
    $cardIDs = array_column($cards, 'KVWKARTENNUMMER');

    if(!$request->has('amountOfTransactions') || !$request->input('amountOfTransactions')) {
        $amount_of_transactions = 5;
    } else {
        $amount_of_transactions = $request->input('amountOfTransactions');
    }

    $transactions = getCardTransactionsFromGWForMultipleCards($cardIDs, $amount_of_transactions, true);
    if(is_object($transactions) && property_exists($transactions, 'errorMessage') && !empty($transactions->errorMessage)) {
        return $transactions;
    }

    foreach($transactions as $cardID => $pTransaction) {
        $tempTransaction = $pTransaction;
        $transactions[$cardID] = new stdClass();
        $transactions[$cardID]->transactions = $tempTransaction;
    }

    foreach($cardIDs as $cardID) {
        $balance = getBalanceAmountForCardID($cardID);
        $transactions[strval($cardID)]->balance = $balance;
    }

    return $transactions;
}


Route::get('/cardids', function (Request $request) {

    $cards = getCardsForCustomer($request->input('contact_person_gguid'));
    $cardIDs = array_column($cards, 'KVWKARTENNUMMER');

    return response()->json( $cardIDs, 200 );

})->middleware(['AuthenticateWithSession']);


Route::post('/change-password', function (Request $request) {

    if(!$request->input('password')) {
        return returnNewErrorObject('Das bestehende Passwort wurde nicht angegeben!', 'no_password', 400);
    }

    if(!$request->input('newPassword')) {
        return returnNewErrorObject('Das neue Passwort wurde nicht angegeben!', 'no_new_password', 400);
    }

    if(!$request->input('newPasswordRepeated')) {
        return returnNewErrorObject('Das neue wiederholte Passwort wurde nicht angegeben!', 'no_new_repeated_password', 400);
    }

    if($request->input('newPassword') != $request->input('newPasswordRepeated')) {
        return returnNewErrorObject('Die beiden neuen Passwörter stimmen nicht überein!', 'password_repeated_password_not_matching', 400);
    }

    $processedCustomerLogin = _processCustomerLogin($request->input('email'), $request->input('password'), $request->input('email'), $request->input('contact_person_gguid'));
    if(isError($processedCustomerLogin)) {
        return returnErrorObject($processedCustomerLogin);
    }

    $username = getGwNutzernameForEMail('*', $request->input('email'));
    if(isError($username)) {
        return returnErrorObject($username);
    }

    $passwordLink = getPasswordRecordForUsernameGGUID($username->GGUID);

    if($passwordLink == NULL || !$passwordLink || count($passwordLink) === 0) {
        Log::error("Fehler in /change-password beim Abrufen von getPasswordRecordForUsernameGGUID (" . $username->GGUID . "): Es wurde keine Verknüpfungen gefunden: " . print_r($passwordLink, true));
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
    } else {
        if(count($passwordLink) > 1) {
            Log::error("Fehler in /change-password beim Abrufen von getPasswordRecordForUsernameGGUID (" . $username->GGUID . "): Es wurden mehrere Verknüpfungen gefunden: " . print_r($passwordLink, true));
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
        }
    }

    $passwordRecord = $passwordLink[0]->fields;
    if(!property_exists($passwordRecord, 'GGUID')) {
        Log::error("Fehler in /change-password beim Abrufen von getPasswordRecordForUsernameGGUID (" . $username->GGUID . "): Objekt hat keine GGUID: " . print_r($passwordRecord, true));
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
    }

    $now = _getGWNowDate();
    $password = $request->input('newPassword');
    $hashedPassword = Hash::make($password);

    $updatePasswordFields = new stdClass();
    $updatePasswordFields->TMPW = $hashedPassword;
    $updatePasswordFields->TMAENDERUNGDATE = $now;
    $updatedPasswordData = updateGwPasswordData($passwordRecord->GGUID, $updatePasswordFields);
    if(isError($updatedPasswordData)) {
        return returnErrorObject($updatedPasswordData);
    }

    return response()->json( new stdClass(), 200 );

})->middleware(['AuthenticateWithSession']);


Route::post('/customer-update-personal-data', function (Request $request) {

    $updatedCustomerUserData = _handleUpdateCustomerUserData($request);

    if(isError($updatedCustomerUserData)) {
        return returnErrorObject($updatedCustomerUserData);
    }

    $response = new stdClass();
    $response->email = $updatedCustomerUserData->MAILFIELDSTR3;
    $response->salutation = $updatedCustomerUserData->ADDRESSTERM;
    $response->gender = $updatedCustomerUserData->GWGENDER;
    $response->firstName = $updatedCustomerUserData->CHRISTIANNAME;
    $response->lastName = $updatedCustomerUserData->NAME;
    $response->street = $updatedCustomerUserData->STREET3;
    $response->zip = $updatedCustomerUserData->ZIP3;
    $response->city = $updatedCustomerUserData->TOWN3;
    $response->country = $updatedCustomerUserData->COUNTRY3;
    $response->phone = $updatedCustomerUserData->PHONEFIELDSTR7;
    $response->birthdate = $updatedCustomerUserData->BIRTHDAY;

    return response()->json( $response, 200 );

})->middleware(['AuthenticateWithSession']);



Route::post('/lock-card', function (Request $request) {

    if(!$request->has('password') || !$request->input('password') || empty($request->input('password'))) {
        return returnNewErrorObject('Es wurde kein Passwort angegeben!', 'no_password',400 );
    }

    if(!$request->has('lockCardCheckbox') || !$request->input('lockCardCheckbox') || empty($request->input('lockCardCheckbox'))) {
        return returnNewErrorObject('Die Checkbox zum CARD sperren wurde nicht bestätigt!', 'no_lockCardCheckbox', 400 );
    }

    if(!$request->has('cardIDToLock') || !$request->input('cardIDToLock') || empty($request->input('cardIDToLock'))) {
        return returnNewErrorObject('Es wurde keine Kartennummer zum Sperren angegeben!', 'no_cardIDToLock', 400 );
    }

    $cardIDToLock = $request->input('cardIDToLock');
    if(!isValidCardIDSyntax($cardIDToLock)) {
        Log::error('Beim Karte Sperren war die Syntax der Kartennummern: ' . $cardIDToLock . ' ungültig!');
        sendErrorNotificationMail('Beim CARD sperren /lock-card wurde missbräuchlich versucht eine andere CardID: ' . $cardIDToLock . ' zu sperren');
        return returnNewErrorObject('Die Kartennummer ist ungültig. Bitte kontaktieren Sie den Support.', 'invalid_cardID', 400);
    }

    $processedCustomerLogin = _processCustomerLogin($request->input('cardIDToLock'), $request->input('password'), $request->input('email'), $request->input('contact_person_gguid'));
    if(isError($processedCustomerLogin)) {
        return returnErrorObject($processedCustomerLogin);
    }

    if(!isContainsCardIDInCustomerSession($request, $cardIDToLock)) {
        Log::error('Dieser Account ist nicht berechtigt die Kartennummer ' . $cardIDToLock . ' abzufragen.');
        sendErrorNotificationMail('Beim CARD sperren /lock-card wurde missbräuchlich versucht die CardID: ' . $cardIDToLock . ' zu sperren');
        return returnNewErrorObject('Dieser Account ist nicht berechtigt diese Kartennummer zu sperren.', 'invalid_cardID', 400);
    }

    $addressGGUID = $request->input('contact_person_gguid');

    $cards = getCardsForCustomer($addressGGUID);
    if(isError($cards) || count($cards) == 0) {
        Log::error("no card_data for " . $addressGGUID . ": " . print_r($cards, true));
        return returnNewErrorObject( 'Für Ihren Account wurden keine Karten gefunden. Bitte wenden Sie sich an den Support', 'no_cardIDs', 500);
    }

    $cardData = getCardForCardID($cardIDToLock);

    if(isError($cardData)) {
        return returnErrorObject($cardData);
    }

    if(!property_exists($cardData, 'GGUID')) {
        return createErrorObject('Kartennummer nicht gefunden. Bitte wenden Sie sich an den Support', 'unknown_error', 500);
    }


    $lockCardInVMResult = _lockOrUnlockCardInVM($cardIDToLock, 'Off');
    if(isError($lockCardInVMResult)) {
        return returnNewErrorObject('Beim Sperren Ihrer Karte ist ein Fehler aufgetreten. Falls das Problem weiterhin besteht, wenden Sie sich bitte an den Support.', 'unknown_error', 500);
    }

    $now = _getGWNowDate();

    $cardFieldsToUpdate = new stdClass();
    $cardFieldsToUpdate->KVWKARTEAKTIVVM = false;
    $cardFieldsToUpdate->GWSTYPE = 'Archiv Karten';
    $cardFieldsToUpdate->GWSSTATUS = 'deaktiviert';
    $cardFieldsToUpdate->KVWKARTENSPERRUNG = true;
    $cardFieldsToUpdate->KVWBELADUNGFREI = false;
    $cardFieldsToUpdate->KVWDATUMSPERRUNG = $now;

    if(!updateGwCardData($cardData->GGUID, $cardFieldsToUpdate)) {
        return returnNewErrorObject('Beim Sperren Ihrer Karte ist ein Fehler aufgetreten. Falls das Problem weiterhin besteht, wenden Sie sich bitte an den Support.', 'unknown_error', 500);
    }

    return response()->json( new stdClass(), 200 );

})->middleware(['AuthenticateWithSession']);


function _lockOrUnlockCardInVM($cardID, $OnOrOff) {
    $valueMasterResponse = Http::withHeaders([
        'provider' => 'trolleymaker',
        'password' => 'poiJJ#9q9'
    ])->post('https://valuemaster.brain-behind.com/CU_WebAPI.asmx/CU_Change_Card', [
        'CardID' => $cardID,
        'On_Off' => $OnOrOff
    ]);

    if($valueMasterResponse->failed()) {
        Log::error('value master response failed for locking card: ' . $valueMasterResponse->body());
        sendErrorNotificationMail('Beim CARD sperren konnte für CardID: ' . $cardID . ' schlug das Sperren im Value Master fehl.');
        return createErrorObject('Es ist ein Fehler aufgetreten.', 'error_locking_card_in_vm', 500 );
    }

    $data = json_decode($valueMasterResponse)->d;

    if($data && $data != NULL) {
        return new stdClass();
    } else {
        Log::error('value master response failed for locking card because no data: ' . $valueMasterResponse->body());
        sendErrorNotificationMail('Beim CARD sperren konnte für CardID: ' . $cardID . ' schlug das Sperren im Value Master fehl.');
        return createErrorObject('Es ist ein Fehler aufgetreten.', 'error_locking_card_in_vm', 500 );
    }
}


Route::post('/interest-contact-form', function (Request $request) {

    if(!$request->has('contactEmail') || empty($request->input('contactEmail'))) {
        return returnNewErrorObject('Es wurde keine E-Mail Adresse angegeben!', 'no_contactEmail', 400);
    }

    if(!$request->has('name') || empty($request->input('name'))) {
        return returnNewErrorObject('Es wurde kein Name angegeben!', 'no_name', 400);
    }

    if(!$request->has('message') || empty($request->input('message'))) {
        return returnNewErrorObject('Es wurde keine Nachricht angegeben!', 'no_message', 400);
    }

    $contactFormData = new stdClass();
    $contactFormData->companyName = $request->input('companyName');
    $contactFormData->salutation = $request->input('salutation');
    $contactFormData->email = $request->input('contactEmail');
    $contactFormData->name = $request->input('name');
    $contactFormData->phone = $request->input('phone');
    $contactFormData->subject = $request->input('subject');
    $contactFormData->message = $request->input('message');
    $contactFormData->region = $request->input('card_name');

    try {
        Mail::to(env('MAIL_MASTER_TO_ADDRESS', 'support@trolleymaker.com'))->send(new InterestContactFormMail($contactFormData));
        Mail::to($contactFormData->email)->send(new InterestContactFormCustomerMail($contactFormData));
        $response = new stdClass();
        $response->success = "Ihre Nachricht wurde erfolgreich abgeschickt.";
        return response()->json($response, 200);
    } catch (Exception $ex) {
        Log::error($ex->getMessage());
        return returnNewErrorObject('Beim Versenden des Kontaktformulares ist ein Fehler aufgetreten.', 'unknown_error', 500);
    }

})->middleware(['AuthenticateWithSession']);


Route::post('/contact-form', function (Request $request) {

    $handleContactForm = _handleContactForm($request);
    if(isError($handleContactForm)) {
        return returnErrorObject($handleContactForm);
    }

    return response()->json( $handleContactForm, 200 );

})->middleware(['AuthenticateWithSession']);

function _handleContactForm($request) {

    if(!$request->has('contactEmail') || empty($request->input('contactEmail'))) {
        return createErrorObject('Es wurde keine E-Mail Adresse angegeben!', 'no_contactEmail', 400);
    }

    if(!$request->has('firstName') || empty($request->input('firstName'))) {
        return createErrorObject('Es wurde kein Vorname angegeben!', 'no_firstName', 400);
    }

    if(!$request->has('lastName') || empty($request->input('lastName'))) {
        return createErrorObject('Es wurde kein Nachname angegeben!', 'no_lastName', 400);
    }

    if(!$request->has('message') || empty($request->input('message'))) {
        return createErrorObject('Es wurde keine Nachricht angegeben!', 'no_message', 400);
    }

    $contactFormData = new stdClass();
    $contactFormData->cardID = $request->input('cardIDs');
    $contactFormData->salutation = $request->input('salutation');
    $contactFormData->email = $request->input('contactEmail');
    $contactFormData->firstName = $request->input('firstName');
    $contactFormData->lastName = $request->input('lastName');
    $contactFormData->message = $request->input('message');
    $contactFormData->region = $request->input('card_name');

    try {
        Mail::to(env('MAIL_MASTER_TO_ADDRESS', 'support@trolleymaker.com'))->send(new ContactFormMail($contactFormData));
        Mail::to($contactFormData->email)->send(new ContactFormCustomerMail($contactFormData));
        $response = new stdClass();
        $response->success = "Ihre Nachricht wurde erfolgreich abgeschickt.";
        return $response;
    } catch (Exception $ex) {
        Log::error($ex->getMessage());
        return createErrorObject('Beim Versenden des Kontaktformulares ist ein Fehler aufgetreten.', 'unknown_error', 500);
    }
}



Route::post('/transfer-balance', function (Request $request) {

    if(!$request->input('cardIDs')) {
        return returnNewErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 400);
    }

    $cardID = trim($request->input('cardIDs'));

    if(!$request->has('cardIDsToTransfer') || $request->input('cardIDsToTransfer') == '' || count($request->input('cardIDsToTransfer')) === 0) {
        return returnNewErrorObject('Es wurde kein neue Kartennummer angegeben.', 'no_cardIDsToTransfer', 400);
    }

    if(!is_array($request->input('cardIDsToTransfer'))) {
        return returnNewErrorObject('Kartennummern konnte nicht gelesen werden. Bitte wenden Sie sich an den Support.', 'invalid_cardIDsToTransfer', 400);
    }

    foreach($request->input('cardIDsToTransfer') as $cardIDToTransfer) {
        if(!ctype_digit($cardIDToTransfer)) {
            return returnNewErrorObject('Die Kartennummer ' . $cardIDToTransfer . ' darf nur aus Ziffern bestehen!', 'invalid_cardIDsToTransfer', 400);
        }
    }

    $personal_data = getGwPersonalDataByGGUID($request->input('contact_person_gguid'));

    if(property_exists($personal_data, 'errorMessage') && !empty($personal_data->errorMessage)) {
        Log::error("no personal data bei transfer-balance für cardID: " . $cardID . ", personal data: " . print_r($personal_data, true));
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support', 'unknown_error', 500);
    }


    $transferBalanceData = new stdClass();
    $transferBalanceData->cardID = $cardID;
    $transferBalanceData->cardIDsToTransfer = $request->input('cardIDsToTransfer');
    $transferBalanceData->email = $personal_data->MAILFIELDSTR3;
    $transferBalanceData->firstName = $personal_data->CHRISTIANNAME;
    $transferBalanceData->lastName = $personal_data->NAME;
    $transferBalanceData->street = $personal_data->STREET3;
    $transferBalanceData->zip = $personal_data->ZIP3;
    $transferBalanceData->city = $personal_data->TOWN3;
    $transferBalanceData->birthdate = gWDateToGermanDate($personal_data->BIRTHDAY);
    $transferBalanceData->isCardActive = $personal_data->NCKARTEAKTIV;
    $transferBalanceData->cardName = $personal_data->NCORTDERANMELDUNG;

    try {
        Mail::to(env('MAIL_MASTER_TO_ADDRESS'))->send(new TransferBalanceMail($transferBalanceData));
        Mail::to($transferBalanceData->email)->send(new TransferBalanceCustomerMail($transferBalanceData));
        return response()->json( "Ihre Anfrage für den Guthabentransfer wurde erfolgreich abgeschickt.", 200 );
    } catch (Exception $ex) {
        Log::error($ex->getMessage());
        return returnNewErrorObject('Beim Versenden der Anfrage für den Guthabentransfer ist ein Fehler aufgetreten.', 'unknown_error', 500);
    }

})->middleware(['AuthenticateWithSession']);


function _handleGetCustomerRegistrationFormValues() {
    $values = _getSuggestedValuesForAddress(['TITLE', 'GWGENDER']);

    if(isError($values)){
        return $values;
    }

    $values['titles'] = $values['TITLE'];
    unset($values['TITLE']);
    $values['genders'] = [];
    foreach ($values['GWGENDER'] as $key => $value) {
        $lowercasedValue = strtolower($value);
        if($lowercasedValue != 'sonstiges' && $lowercasedValue != 'sonstige') {
            array_push($values['genders'], $lowercasedValue);
        }
    }
    unset($values['GWGENDER']);

    return $values;
}

Route::get('/customer-registration-form-values', function (Request $request) {

    $values = _handleGetCustomerRegistrationFormValues();

        if($request->has('id') && !empty($request->input('id'))) {
                $hashedGGUID = $request->input('id');
                $decryptedGGUID = decryptURLGGUID($hashedGGUID);
                if(isError($decryptedGGUID) || empty($decryptedGGUID)) {
                        Log::error('Fehler beim Entschlüsseln der GGUID aus URL Parameter. decryptedGGUID: ' . print_r($decryptedGGUID, true));
                        return response()->json($values, 200);
                }

                $prefillData = isPrefilledInterestPartnerOrCustomer($decryptedGGUID, $hashedGGUID);

                // $prefillData->company_data: company_data is just address data of customers gguid
                if(!property_exists($prefillData, 'isAllowedToPrefill') || $prefillData->isAllowedToPrefill == false || !property_exists($prefillData, 'company_data') || !is_object($prefillData->company_data)) {
                        return response()->json($values, 200);
                }

                $customer_data = $prefillData->company_data;
                $values['email'] = property_exists($customer_data, 'MAILFIELDSTR3') && !empty($customer_data->MAILFIELDSTR3) ? $customer_data->MAILFIELDSTR3 : '';
                $values['title'] = property_exists($customer_data, 'TITLE') && !empty($customer_data->TITLE) ? $customer_data->TITLE : '';
                $values['gender'] = property_exists($customer_data, 'GWGENDER') && !empty($customer_data->GWGENDER) ? $customer_data->GWGENDER : '';
                $values['firstName'] = property_exists($customer_data, 'CHRISTIANNAME') && !empty($customer_data->CHRISTIANNAME) ? $customer_data->CHRISTIANNAME : '';
                $values['lastName'] = property_exists($customer_data, 'NAME') && !empty($customer_data->NAME) ? $customer_data->NAME : '';
                $values['street'] = property_exists($customer_data, 'STREET3') && !empty($customer_data->STREET3) ? $customer_data->STREET3 : '';
                $values['zip'] = property_exists($customer_data, 'ZIP3') && !empty($customer_data->ZIP3) ? $customer_data->ZIP3 : '';
                $values['city'] = property_exists($customer_data, 'TOWN3') && !empty($customer_data->TOWN3) ? $customer_data->TOWN3 : '';
                $values['country'] = property_exists($customer_data, 'COUNTRY3') && !empty($customer_data->COUNTRY3) ? $customer_data->COUNTRY3 : '';
                $values['phone'] = property_exists($customer_data, 'PHONEFIELDSTR7') && !empty($customer_data->PHONEFIELDSTR7) ? $customer_data->PHONEFIELDSTR7 : '';
                $values['birthdate'] = property_exists($customer_data, 'BIRTHDAY') && !empty($customer_data->BIRTHDAY) ? $customer_data->BIRTHDAY : '';
                $values['cardID'] = property_exists($customer_data, 'NCKARTENNUMMER') && !empty($customer_data->NCKARTENNUMMER) ? $customer_data->NCKARTENNUMMER : '';
                $values['prefilledPartner'] = $hashedGGUID;
        }

    if(isError($values)){
        return returnErrorObject($values);
    }

    return response()->json($values, 200);
});


Route::post('/customer-registration', function (Request $request) {
    $returnFromHandle = _handleCustomerRegistration($request);

    if(isError($returnFromHandle)) {
        return returnErrorObject($returnFromHandle);
    }

    return response()->json($returnFromHandle, 200);
});

function _handleCustomerRegistration($request){
    foreach(array('password', 'passwordRepeated', 'cardID', 'email', 'emailRepeated', 'gender', 'firstName', 'lastName',
                  'street', 'zip', 'city', 'country', 'birthdate', 'conditionsConsent') as $input) {
        if($request->input($input) == NULL || $request->input($input) == '') {
            return createErrorObject('Es wurden nicht alle erforderlichen Felder ausgefüllt!'. $input, 'not_all_fields_filledOut', 400);
        }
    }

    if($request->input('email') != $request->input('emailRepeated')) {
        return createErrorObject('Die beiden E-Mail Adressen stimmen nicht überein.', 'emailRepeated_unequal_email', 400);
    }

    if(!isValidCardIDSyntax($request->input('cardID'))) {
        return createErrorObject('Die Kartennummer ist ungültig', 'invalid_cardID', 400);
    }

    if($request->input('password') != $request->input('passwordRepeated')) {
        return createErrorObject('Die beiden Passwörter stimmen nicht überein!', 'passwordRepeated_unequal_password', 400 );
    }

    $password = $request->input('password');

    if(strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/\d/', $password) || !preg_match('/[!\$%\(\)\*,\-\.\?@\^_~]/', $password)) {
        return createErrorObject('Das Passwort muss mindestens 8 Zeichen lang sein und 1 Nummer, 1 Großbuchstaben und 1 Sonderzeichen (!$%()*,-.?@^_~) enthalten.', 'invalid_password', 400 );
    }

    if(!preg_match('/^[a-zA-ZäöüßÄÖÜ0-9!$%()*,-.?@^_~]+$/', $password)) {
        return createErrorObject('Das Passwort enthält ungültige Zeichen. Neben Buchstaben und Ziffern sind nur folgende Sonderzeichen erlaubt:     !$%()*,-.?@^_~', 'invalid_password_characters', 400 );
    }

    if(strlen($request->input('zip')) != 4 && strlen($request->input('zip')) != 5) {
        return createErrorObject('Die Postleitzahl darf nur aus 4 oder 5 Zahlen bestehen.', 'zip_length_invalid', 400 );
    }

    if(strlen($request->input('country')) != 2) {
        Log::error('Fehler bei Kundenregistrierung: Ungültiger Ländercode: ' . $request->input('country'));
        return createErrorObject('Ungültiger Ländercode. Bitte wenden Sie sich an den Support.', 'invalid_countrycode', 400 );
    }

    if(!validateDate($request->input('birthdate') . ' 00:00:00', 'd.m.Y H:i:s')) {
        if(!validateDateIsISOFormatWithoutTime($request->input('birthdate'))) {
            Log::error('Fehler bei Kundenregistrierung: Geburtsdatum ist ungültig: ' . $request->input('birthdate'));
            return createErrorObject('Das Geburtsdatum ist ungültig.', 'birthdate_invalid', 400 );
        }
    }

    $registerData = new stdClass();
    $registerData->email = $request->input('email');
    $registerData->cardID = trim($request->input('cardID'));
    $registerData->zip = $request->input('zip');
    $registerData->country = $request->input('country');
    $birthDate = new DateTime($request->input('birthdate') . ' 00:00:00', new DateTimeZone('Europe/Berlin'));
    $registerData->birthdate = $birthDate->format('Y-m-d\TH:i:s');
    $registerData->password = $request->input('password');

    $checkIfUsernameAlreadyExists = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/query', [
        'query' => 'SELECT TMNUTZERMAIL, TMNUTZERNAME FROM NUTZERNAMEN WHERE TMNUTZERMAIL="' . $registerData->email . '" OR TMNUTZERNAME="' . $registerData->email . '"'
    ]);

    if($checkIfUsernameAlreadyExists->failed()) {
        Log::error('Es konnte für E-Mail: ' . $registerData->email . ' nicht geprüft werden, ob der NUTZERNAME bereits existiert.');
        return createErrorObject('Es ist ein Fehler aufgetreten. Es konnte nicht geprüft werden, ob die E-Mail-Adresse bereits registriert wurde. Bitte wenden Sie sich an den Support.', 'unknown_if_username_already_registered', 500 );
    }

    $dataUsernameAlreadyRegistered = json_decode($checkIfUsernameAlreadyExists);
    if($dataUsernameAlreadyRegistered && count($dataUsernameAlreadyRegistered) > 0) {
        return createErrorObject('Es existiert bereits ein Account mit dieser E-Mail-Adresse. Sie können Ihre neue CARD zu Ihrem bestehenden Account hinzufügen.', 'email_already_registered', 400 );
    }



    $checkIfCardIDAlreadyRegistered = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/query', [
        'query' => 'SELECT GGUID, KVWKARTENNUMMER, KVWKARTEREGISTRIERT, KVWISTTESTKARTE FROM kartenverwaltung WHERE KVWKARTENNUMMER="' . $registerData->cardID . '"'
    ]);

    if($checkIfCardIDAlreadyRegistered->failed()) {
        return createErrorObject('Es ist ein Fehler aufgetreten. Es konnte nicht geprüft werden, ob die Kartennummer bereits registriert wurde. Bitte wenden Sie sich an den Support.', 'unknown_if_cardID_already_registered', 500 );
    }

    $dataCardAlreadyRegistered = json_decode($checkIfCardIDAlreadyRegistered);

    if($dataCardAlreadyRegistered != NULL && count($dataCardAlreadyRegistered) > 0 && count($dataCardAlreadyRegistered[0]->rows) === 1) {
        if($dataCardAlreadyRegistered[0]->rows[0]->KVWKARTEREGISTRIERT == true) {
            return createErrorObject('Die CARD wurde bereits registriert!', 'cardID_already_registered', 400 );
        }
    } else {
        Log::error("Bei der Kundenregistrierung wurden kein oder mehrere Datensätze für die Kartennummer " . $registerData->cardID . " gefunden.: " . print_r($dataCardAlreadyRegistered, true));
        return createErrorObject('Es ist ein Fehler aufgetreten. Es konnte nicht geprüft werden, ob die Kartennummer bereits registriert wurde. Bitte wenden Sie sich an den Support.', 'unknown_if_cardID_already_registered', 500 );
    }



    $getAddressForCardID = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/query', [
        'query' => 'SELECT * FROM address WHERE NCKARTENNUMMER="' . $registerData->cardID . '" AND GWSTYPE="Kunde"'
    ]);

    if($getAddressForCardID->failed()) {
        Log::error('Es ist ein Fehler aufgetreten. Es die Kartennummer ' . $registerData->cardID . ' wurde nicht in GW nicht gefunden');
        return createErrorObject('Es ist ein Fehler aufgetreten. Die Kartennummer wurde nicht gefunden. Bitte wenden Sie sich an den Support.', 'unknown_if_card_found', 500 );
    }

    $addressForCardID = json_decode($getAddressForCardID);

    $addressCount = ($addressForCardID != NULL && count($addressForCardID) > 0 && property_exists($addressForCardID[0], 'rows')) ? count($addressForCardID[0]->rows) : 0;

    if($addressCount > 1) {
        Log::error("Bei der Kundenregistrierung wurden mehrere ADDRESS Datensätze für die Kartennummer " . $registerData->cardID . " gefunden.");
        sendErrorNotificationMail('Bei der Kundenregistrierung wurden mehrere ADDRESS Datensätze für die Kartennummer ' . $registerData->cardID . ' gefunden.');
        return createErrorObject('Es ist ein Fehler aufgetreten. Es wurden mehrere Adressen für diese Kartennummer gefunden. Bitte wenden Sie sich an den Support.', 'multiple_addresses_found', 500 );
    }

    $createNewAddress = false;

    if($addressCount === 1) {
        if(property_exists($addressForCardID[0]->rows[0], 'NCREGISTRIERTSEIT') && $addressForCardID[0]->rows[0]->NCREGISTRIERTSEIT != NULL) {
            return createErrorObject('Die CARD wurde bereits registriert!', 'cardID_already_registered', 400 );
        }

        $address = $addressForCardID[0]->rows[0];
    } else {
        $createNewAddress = true;
    }



    $isNewSystem = $request->input('regionName') != NULL && $request->input('regionName') != '' && $request->input('cardTypeName') != NULL && $request->input('cardTypeName') != '';

    if($isNewSystem) {
        $registerData->region = $request->input('regionName');
        $registerData->cardName = $request->input('cardTypeName');
        $registerData->isMitarbeitercard = false;
    } else {

        $clusterResponse = Http::withHeaders([
            'provider' => 'trolleymaker',
            'password' => 'poiJJ#9q9'
        ])->post('https://valuemaster.brain-behind.com/CU_WebAPI.asmx/CU_Check_Customer_InCluster', [
            'CardID' =>  $registerData->cardID
        ]);

        if($clusterResponse && $clusterResponse != NULL) {
            $cluster_data = json_decode($clusterResponse)->d;

            if($cluster_data && $cluster_data != NULL) {
                if($cluster_data->Cluster && count($cluster_data->Cluster) > 0) {

                    foreach($cluster_data->Cluster as $current_cluster) {
                        if($current_cluster->ClusterName == "alle_ettenheimcard") {
                            $registerData->region = "Ettenheim";
                            $registerData->cardName = "EttenheimCARD";
                        } else if($current_cluster->ClusterName == "alle_lahrcard") {
                            $registerData->region = "Lahr";
                            $registerData->cardName = "LahrCARD";
                        } else if($current_cluster->ClusterName == "alle_wuermtalcard" || $current_cluster->ClusterName == "alle_würmtalcard") {
                            $registerData->region = "Würmtal";
                            $registerData->cardName = "WürmtalCARD";
                        } else if($current_cluster->ClusterName == "alle_schwabachcard") {
                            $registerData->region = "Schwabach";
                            $registerData->cardName = "SchwabachCARD";
                        } else if($current_cluster->ClusterName == "alle_herbolzheimcard") {
                            $registerData->region = "Herbolzheim";
                            $registerData->cardName = "Herbolzheim Karte";
                        } else if($current_cluster->ClusterName == "alle_badsaeckingencard") {
                            $registerData->region = "Bad Säckingen";
                            $registerData->cardName = "BadSaeckingenCARD";
                        } else if($current_cluster->ClusterName == "alle_heimatshoppencard") {
                            $registerData->region = "Heimatshoppen";
                            $registerData->cardName = "HeimatshoppenCARD";
                        } else if($current_cluster->ClusterName == "alle_lecard") {
                            $registerData->region = "Leinfelden-Echterdingen";
                            $registerData->cardName = "LE CARD";
                        } else if($current_cluster->ClusterName == "alle_landshutcard") {
                            $registerData->region = "Landshut";
                            $registerData->cardName = "LandshutCARD";
                        } else if($current_cluster->ClusterName == "alle_wuncard") {
                            $registerData->region = "Wunsiedel";
                            $registerData->cardName = "WunCARD";
                        } else if($current_cluster->ClusterName == "alle_kenzingencard") {
                            $registerData->region = "Kenzingen";
                            $registerData->cardName = "KenzingenCARD";
                        } else if($current_cluster->ClusterName == "alle_trocard") {
                            $registerData->region = "Troisdorf";
                            $registerData->cardName = "TroCARD";
                        } else if($current_cluster->ClusterName == "alle_balingencard") {
                            $registerData->region = "Balingen";
                            $registerData->cardName = "BalingenCARD";
                        } else if($current_cluster->ClusterName == "alle_viertaelercard") {
                            $registerData->region = "Plettenberg";
                            $registerData->cardName = "ViertälerCARD";
                        } else if($current_cluster->ClusterName == "alle_neuriedcard") {
                            $registerData->region = "Neuried";
                            $registerData->cardName = "NeuriedCARD";
                        } else if($current_cluster->ClusterName == "alle_abensbergcard") {
                            $registerData->region = "Abensberg";
                            $registerData->cardName = "AbensbergCARD";
                        } else if($current_cluster->ClusterName == "alle_calwcard") {
                            $registerData->region = "Calw";
                            $registerData->cardName = "CalwCARD";
                        } else if($current_cluster->ClusterName == "alle_hucard") {
                            $registerData->region = "Henstedt-Ulzburg";
                            $registerData->cardName = "Henstedt-Ulzburg SmartCARD";
                        } else if($current_cluster->ClusterName == "alle_foerdecard") {
                            $registerData->region = "Flensburg";
                            $registerData->cardName = "FördeCARD";
                        } else if($current_cluster->ClusterName == "alle_ratingencard") {
                            $registerData->region = "Ratingen";
                            $registerData->cardName = "RatingenCARD";
                        } else if($current_cluster->ClusterName == "alle_haslachcard") {
                            $registerData->region = "Haslach";
                            $registerData->cardName = "HaslachCARD";
                        } else if($current_cluster->ClusterName == "alle_erlebnisregioneuropaparkcard") {
                            $registerData->region = "Erlebnisregion Europa-Park";
                            $registerData->cardName = "Erlebnisregion Europa-Park CARD";
                        } else if($current_cluster->ClusterName == "alle_srcard") {
                            $registerData->region = "Rotenburg an der Wümme";
                            $registerData->cardName = "SR CARD";
                        } else if ($current_cluster->ClusterName == "alle_echtfreiburgcard") {
                            $registerData->region = "Freiburg im Breisgau";
                            $registerData->cardName = "ECHT FREIBURG CARD";
                        } else if ($current_cluster->ClusterName == "alle_pfulbencard") {
                            $registerData->region = "Pfullingen";
                            $registerData->cardName = "PfulbenCARD";
                        } else if ($current_cluster->ClusterName == "alle_badenbadencard") {
                            $registerData->region = "Baden-Baden";
                            $registerData->cardName = "Baden-Baden CARD";
                        } else if ($current_cluster->ClusterName == "alle_dettingenanderermscard") {
                            $registerData->region = "Dettingen an der Erms";
                            $registerData->cardName = "DETTINGEN ERMSCARD";
                        } else if ($current_cluster->ClusterName == "alle_0711card") {
                            $registerData->region = "Stuttgart Mitte";
                            $registerData->cardName = "0711CARD";
                        } else if ($current_cluster->ClusterName == "alle_badwaldseecitycard") {
                            $registerData->region = "Bad Waldsee";
                            $registerData->cardName = "Bad Waldsee CityCARD";
                        } else if ($current_cluster->ClusterName == "alle_besigheimcard") {
                            $registerData->region = "Besigheim";
                            $registerData->cardName = "BesigheimCARD";
                        } else if ($current_cluster->ClusterName == "alle_huecard") {
                            $registerData->region = "Hückelhoven";
                            $registerData->cardName = "HÜCARD";
                        } else if ($current_cluster->ClusterName == "alle_neubulachcard") {
                            $registerData->region = "Neubulach";
                            $registerData->cardName = "NeubulachCARD";
                        } else if ($current_cluster->ClusterName == "alle_stadtgutscheintroisdorf") {
                            $registerData->region = "Troisdorf Stadt";
                            $registerData->cardName = "Stadtgutschein Troisdorf";
                        }

                        if($current_cluster->ClusterName == "alle_mitarbeitercard" || $current_cluster->ClusterName == "alle_mitarbeitercards") {
                            $registerData->isMitarbeitercard = true;
                        }
                    }

                    if(!property_exists($registerData, 'isMitarbeitercard')) {
                        $registerData->isMitarbeitercard = false;
                    }

                    if(!property_exists($registerData, "region") || strlen($registerData->region) == 0 || $registerData->region == "") {
                        return createErrorObject('Es ist ein Fehler aufgetreten. Die Kartennummer konnte keiner Region zugewiesen werden. Bitte wenden Sie sich an den Support.', 'unknown_region', 400 );
                    }
                } else {
                    return createErrorObject('Es ist ein Fehler aufgetreten. Die Kartennummer konnte keiner Region zugewiesen werden. Bitte wenden Sie sich an den Support.', 'unknown_region', 400 );
                }
            } else {
                return createErrorObject('Es ist ein Fehler aufgetreten. Die Kartennummer konnte keiner Region zugewiesen werden. Bitte wenden Sie sich an den Support.', 'unknown_region', 400 );
            }
        } else {
            return createErrorObject('Es ist ein Fehler aufgetreten. Die Kartennummer konnte keiner Region zugewiesen werden. Bitte wenden Sie sich an den Support.', 'unknown_region', 400 );
        }

    } // end else (cluster lookup fallback)


    $registerData->gender = $request->input('gender');
    $registerData->firstName = $request->input('firstName');
    $registerData->lastName = $request->input('lastName');
    $registerData->title = $request->input('title');

    $guessedSalutation = _guessSalutationFromGW($registerData->firstName, $registerData->lastName, $registerData->gender, $registerData->title, $registerData->country);
    $registerData->addressterm = $guessedSalutation->addressterm;
    $registerData->addressletter = $guessedSalutation->addressletter;

    $registerData->street = $request->input('street');
    $registerData->city = $request->input('city');
    $registerData->phone = $request->input('phone');
    $registerData->marketingAdsConsent = $request->input('marketingAdsConsent');
    $registerData->newsletterConsent = $request->input('newsletterConsent');


    $dateNow = new DateTime('now');
    $dateNow->setTimezone(new DateTimeZone('Europe/Berlin'));
    $registerData->registeredSince = $dateNow->format('Y-m-d\TH:i:s');

    if(!$isNewSystem) {
        $valueMasterResponse = Http::withHeaders([
                                                     'provider' => 'trolleymaker',
                                                     'password' => 'poiJJ#9q9',
                                                 ]
        )->post('https://valuemaster.brain-behind.com/CU_WebAPI.asmx/CU_Modify_User', [
            'Password'       => $registerData->password,
            'CardID'         => $registerData->cardID,
            'Email'          => $registerData->email,
            'ReqCase'        => 'INSERT',
            'Title'          => '',
            'Gender'         => '',
            'PreName'        => '',
            'Name'           => '',
            'Street'         => '',
            'ZIP'            => '',
            'City'           => '',
            'Phone'          => '',
            'Mobile'         => '',
            'Birthday'       => '',
            'country'        => '',
            'SMSagreement'   => '1',
            'emailagreement' => '1',
            'UserInterest'   => [],
            'UserCategory'   => [],
            'Herkunft'       => '',
            'AdditionalKeys' => [],
            'AdditionalData' => [],
        ]
        );

        Log::debug("valueMasterResponse:" . $valueMasterResponse->body());

        $data = json_decode($valueMasterResponse)->d;

        if (!$data || $data == NULL) {
            Log::error("Fehler bei Kunden-Registrierung bei ValueMaster Registrierung");

            return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
        }

        if (($data->error != NULL && $data->error != "") || ($data->status != NULL && $data->status != "" && $data->status == "NOK")) {
            Log::error("Fehler bei Kunden-Registrierung bei ValueMaster: " . $data->error);

            return createErrorObject('Bei der Registrierung ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut oder wenden Sie sich an den Support.', 'registration_error', 500);
        }
    }

    $fields = new stdClass();
    $fields->GWSTYPE = 'Kunde';
    $fields->MAILFIELDSTR3 = $registerData->email;
    $fields->TITLE = $registerData->title;
    $fields->GWGENDER = $registerData->gender;
    $fields->ADDRESSTERM = $registerData->addressterm;
    $fields->ADDRESSLETTER = $registerData->addressletter;
    $fields->CHRISTIANNAME = $registerData->firstName;
    $fields->NAME = $registerData->lastName;
    $fields->STREET3 = $registerData->street;
    $fields->TOWN3 = $registerData->city;
    $fields->ZIP3 = $registerData->zip;
    $fields->COUNTRY3 = $registerData->country;
    $fields->PHONEFIELDSTR7 = $registerData->phone;
    $fields->BIRTHDAY = $registerData->birthdate;
    $fields->NCKARTEAKTIV = true;
    $fields->NCKONTOAKTIVIERT = true;
    $fields->NCWERBUNGEINWILLIGUNG = $registerData->marketingAdsConsent;
    $fields->NCNLANGEMELDET = $registerData->newsletterConsent;
    $fields->NCMITARBEITERCARD = $registerData->isMitarbeitercard;
    $fields->NCREGION = $registerData->region;
    $fields->NCORTDERANMELDUNG = $registerData->cardName;
    $fields->NCREGISTRIERTSEIT = $registerData->registeredSince;

    if($createNewAddress) {
        $fields->NCKARTENNUMMER = $registerData->cardID;
        $createdAddressGGUID = _createAddressInGw($fields);
        if(isError($createdAddressGGUID)) {
            Log::error("Bei der Kundenregistrierung konnte kein neuer Adress Datensatz für Kartennummer " . $registerData->cardID . " erstellt werden.");
            sendErrorNotificationMail("Bei der Kundenregistrierung konnte kein neuer Adress Datensatz für Kartennummer " . $registerData->cardID . " erstellt werden.");
            return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'could_not_create_account', 500 );
        }
        $addressGGUID = $createdAddressGGUID;
    } else {
        if(!updateGwAddressData($address->GGUID, $fields)) {
            Log::error("Bei der Kundenregistrierung konnte Adress Datensatz für Kartennummer " . $registerData->cardID . " nicht geupdatet / registriert werden.");
            sendErrorNotificationMail("Bei der Kundenregistrierung konnte Adress Datensatz für Kartennummer " . $registerData->cardID . " nicht geupdatet / registriert werden.");
            return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'could_not_update_account', 500 );
        }
        $addressGGUID = $address->GGUID;
    }

    $addGwLinkResponse = _linkCardToAddress($dataCardAlreadyRegistered[0]->rows[0]->GGUID, $addressGGUID, 'TMKVWADRESSE');
    if(isError($addGwLinkResponse)) {
        return $addGwLinkResponse;
    }

    //update card
    $cardFields = new stdClass();
    $cardFields->KVWKARTEREGISTRIERT = true;
    $cardFields->KVWKARTEREGISTRIERTSEIT = $registerData->registeredSince;

    if(!updateGwCardData($dataCardAlreadyRegistered[0]->rows[0]->GGUID, $cardFields)) {
        Log::error("update-employer-cards: failed updating CARD: " . $dataCardAlreadyRegistered[0]->rows[0]->GGUID . " Fields: " . print_r($cardFields, true));
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.' , 'unknown_error', 500);
    }

    if($registerData->newsletterConsent == true && $registerData->region != "Bad Säckingen") {
        $regionData = getRegionData($registerData->region, $registerData->cardName, ['acf.inxmail_verteilerlist_id']);
        if(!(!isError($regionData) && property_exists($regionData, 'acf') && property_exists($regionData->acf, 'inxmail_verteilerlist_id'))) {
            Log::error('Es ist ein Fehler bei der Newsletteranmeldung/-abmeldung aufgetreten, die Regionsdaten konnten nicht abgerufen werden');
        } else {
            $registerData->inxmailListId = $regionData->acf->inxmail_verteilerlist_id;
            $registerData->gguid = $addressGGUID;
            if(!updateInxmailUser('activate', $registerData)) {
                Log::error('Es ist ein Fehler bei der Newsletteranmeldung/-abmeldung aufgetreten');
            }
        }
    }

    $now = _getGWNowDate();
    $usernameAndPasswordResponse = createGwUsernameAndPassword($addressGGUID, $fields->MAILFIELDSTR3, $registerData->password, $now, true);
    if(isError($usernameAndPasswordResponse)) {
        Log::error('Beim Customer Registration konnte für Datensatz ' . $addressGGUID . ' kein Nutzername und Password Link angelegt werden.');
        sendErrorNotificationMail('Beim Customer Registration konnte für Datensatz ' . $addressGGUID . ' kein Nutzername und Password Link angelegt werden.');
    }

    if($dataCardAlreadyRegistered[0]->rows[0]->KVWISTTESTKARTE != true) {
        Mail::to($registerData->email)->send(new RegistrationCustomerMail($registerData));
    }

    $response = new stdClass();
    $response->cardID = $registerData->cardID;
    $response->region = $registerData->region;
    $response->cardName = $registerData->cardName;
    $response->cardHolderId = $addressGGUID;

    if(strtolower($response->region) == 'landshut') {
        $perk = _getNextAvailablePerk();
        /*
        if($perk == NULL || isError($perk)) {
            sendErrorNotificationMail('Es konnte kein Perk für den Kunden ' . $registerData->email . ' ausgegeben werden.');
            Log::error('Es konnte kein Perk für den Kunden ' . $registerData->email . ' ausgegeben werden.');
        }
        */

        if(!empty($perk) && property_exists($perk, 'GGUID')) {
            if(updateGwPerkData($perk->GGUID, ['TMSTATUSGUTSCHEIN' => 'ausgegeben'])) {
                $addGwLink = Http::withHeaders([
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Accept' => 'application/json',
                    'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
                    'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
                ])->post(env('GW_API_BASE') . '/type/ADDRESS/' . $addressGGUID . '/dossier?gguid2=' . $perk->GGUID . '&attribute=Prod2Kunde&object-type2=BSPRODUCT');

                if($addGwLink->failed()) {
                    Log::error("Fehler beim Erstellen einer neuen Verknüpfung von Adresse/Kunde zu Product/Perk: " . $addGwLink->body());
                    sendErrorNotificationMail("Fehler beim Erstellen einer neuen Verknüpfung von Adresse/Kunde zu Product/Perk: " . $addGwLink->body());
                }
            } else {
                sendErrorNotificationMail('Es konnte der Perk bei der Kundenregistrierung für den Kunden ' . $registerData->email . ' nicht geupdatet werden.');
                Log::error('Es konnte der Perk bei der Kundenregistrierung für den Kunden ' . $registerData->email . ' nicht geupdatet werden.');
            }
        }
    }

    return $response;
}



Route::post('/reset-password', function (Request $request) {

    if(!$request->has('email') || !$request->input('email')) {
        return returnNewErrorObject('Es wurde keine E-Mail-Adresse angegeben!', 'no_email', 400);
    }

    if(!str_contains($request->input('email'), '@')) {
        return returnNewErrorObject('Die E-Mail-Adresse ist ungültig!', 'invalid_email', 400);
    }

    $email = $request->input('email');



    $username = getGwNutzernameForEMail('*', $email);
    if(isError($username)) {
        return returnErrorObject($username);
    }

    $user_exists = false;
    $card_name = 'CARD';

    if($username != NULL && property_exists($username, 'GGUID')) {
        //username record exists
        $user_exists = true;
        $personal_data = _getAddressForUsername($username->GGUID);
        if(isError($personal_data)) {
            return returnErrorObject($personal_data);
        }
        if($personal_data == NULL) {
            Log::error('Fehler bei /reset-password: Nutzername wurde gefunden aber nicht zugehörige Adresse: ' . print_r($username ,true));
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support', 'no_personal_data', 500);
        }
        if(property_exists($personal_data, 'NCORTDERANMELDUNG')) {
            $card_name = $personal_data->NCORTDERANMELDUNG;
        }

    } else {
            //username record not exists
            $addressRecord = getGwAddressForLoginEMail('GGUID, NCORTDERANMELDUNG,NCREGISTRIERTSEIT,GWSTYPE', $email);
            if (isError($addressRecord)) {
                    return returnErrorObject($addressRecord);
            }

            if ($addressRecord != NULL && property_exists($addressRecord, 'GGUID')) {
                    $GWSType = $addressRecord->GWSTYPE;
                    if($GWSType != 'Kunde') {
                        $user_exists = true;
                        if (property_exists($addressRecord, 'NCORTDERANMELDUNG')) {
                            $card_name = $addressRecord->NCORTDERANMELDUNG;
                        }
                    }else{
                        return returnNewErrorObject( "Es wurde kein registrierter Account mit dieser E-Mail-Adresse gefunden. Bitte führen Sie zuerst ihre Registrierung durch.", 500 );
                    }
            }
    }

    if($user_exists == false) {
        Log::error('Bei Passwort zurücksetzen keinen Account für Email ' . $email . ' gefunden.');
        return response()->json( "Falls Ihr Account gefunden wurde, sollten Sie in diesem Moment eine E-Mail erhalten haben, mit einem Bestätigungslink, um Ihr Passwort zurückzusetzen. Bitte schauen Sie auch in Ihrem Spam-Ordner nach.", 200 );
    }

    $token = generateRandomPasswordToken();

    $session_token = (string) Str::uuid();

    $sessionDataToInsert = [
        'id' => $session_token,
        'email' => $email,
        'password_reset_token' => $token,
        'password_reset_timestamp' => Carbon::now(),
        'user_role' => '',
        'card_name' => $card_name,
        'created_at' => Carbon::now()
    ];

    DB::table('mycitycards_sessions')->insert($sessionDataToInsert);

    $resetPasswordData = new stdClass();
    $resetPasswordData->email = $email;
    $resetPasswordData->link = "https://mycity.cards/new-password?t=" . $token;
    $resetPasswordData->card_name = $card_name;

    try {
        Mail::to($email)->send(new ResetPasswordCustomerMail($resetPasswordData));
        return response()->json( "Falls Ihr Account gefunden wurde, sollten Sie in diesem Moment eine E-Mail erhalten haben, mit einem Bestätigungslink, um Ihr Passwort zurückzusetzen. Bitte schauen Sie auch in Ihrem Spam-Ordner nach.", 200 );
    } catch (Exception $ex) {
        Log::error($ex->getMessage());
        return returnNewErrorObject('Beim Versenden der E-Mail mit dem Bestätigungslink ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.', 'send_email_failed', 500);
    }
});


Route::post('/new-password', function (Request $request) {

    if(!$request->has('t') || !$request->input('t')) {
        return returnNewErrorObject('Der Link ist ungültig!', 'no_token', 400);
    }

    if($request->input('password') != $request->input('passwordRepeated')) {
        return returnNewErrorObject('Die beiden Passwörter stimmen nicht überein!', 'passwordRepeated_unequal_password', 400 );
    }

    $password = $request->input('password');
    $hashedPassword = Hash::make($password);

    if(strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/\d/', $password) || !preg_match('/[!\$%\(\)\*,\-\.\?@\^_~]/', $password)) {
        return returnNewErrorObject('Das Passwort muss mindestens 8 Zeichen lang sein und 1 Nummer, 1 Großbuchstaben und 1 Sonderzeichen (!$%()*,-.?@^_~) enthalten.', 'invalid_password', 400 );
    }

    if(!preg_match('/^[a-zA-ZäöüßÄÖÜ0-9!$%()*,-.?@^_~]+$/', $password)) {
        return returnNewErrorObject('Das Passwort enthält ungültige Zeichen. Neben Buchstaben und Ziffern sind nur folgende Sonderzeichen erlaubt:     !$%()*,-.?@^_~', 'invalid_password_characters', 400 );
    }

    $user = DB::table('mycitycards_sessions')->where('password_reset_token', $request->input('t'))->first(['id', 'email', 'password_reset_token', 'password_reset_timestamp', 'card_name']);
    if($user == NULL || !$user) {
        return returnNewErrorObject('Der Link ist ungültig!', 'invalid_link', 403);
    }

    $email = $user->email;



    $username = getGwNutzernameForEMail('*', $email);
    if(isError($username)) {
        return returnErrorObject($username);
    }

    $now = _getGWNowDate();

    if($username != NULL && property_exists($username, 'GGUID')) {
        //username record exists

        $passwordLink = getPasswordRecordForUsernameGGUID($username->GGUID);

        if($passwordLink == NULL || !$passwordLink || count($passwordLink) === 0) {
            $newPasswordResult = createGwPasswordForUsername($username->GGUID, $hashedPassword, $now);
            if(isError($newPasswordResult)) {
                return returnErrorObject($newPasswordResult);
            }
        } else {
            if(count($passwordLink) > 1) {
                Log::error("Fehler in /new-password beim Abrufen von getPasswordRecordForUsernameGGUID (" . $username->GGUID . "): Es wurden mehrere Verknüpfungen gefunden: " . print_r($passwordLink, true));
                return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
            }

            $passwordRecord = $passwordLink[0]->fields;
            if(!property_exists($passwordRecord, 'GGUID')) {
                Log::error("Fehler in /new-password beim Abrufen von getPasswordRecordForUsernameGGUID (" . $username->GGUID . "): Objekt hat keine GGUID: " . print_r($passwordRecord, true));
                return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
            }

            $updatePasswordFields = new stdClass();
            $updatePasswordFields->TMPW = $hashedPassword;
            $updatePasswordFields->TMAENDERUNGDATE = $now;
            $updatedPasswordData = updateGwPasswordData($passwordRecord->GGUID, $updatePasswordFields);
            if(isError($updatedPasswordData)) {
                return returnErrorObject($updatedPasswordData);
            }
        }
    } else {

        //username record not exists
        $addressRecord = getGwAddressForLoginEMail('*', $email);
        if(isError($addressRecord)) {
            return returnErrorObject($addressRecord);
        }

        if($addressRecord == NULL || !property_exists($addressRecord, 'GGUID')) {
            Log::error('Beim Passwort zurücksetzen konnte für E-Mail ' . $email . ' kein Addressdatensatz gefunden werden.');
            sendErrorNotificationMail('Beim Passwort zurücksetzen konnte für E-Mail ' . $email . ' kein Addressdatensatz gefunden werden.');
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
        }

        $usernameAndPasswordResponse = createGwUsernameAndPassword($addressRecord->GGUID, $email, $password, $now, true, $now);
        if(!isError($usernameAndPasswordResponse)) {
            $clearOldInteressentPWDResponse = updateGwAddressData($addressRecord->GGUID, ['NCINTERESSENTPWD' => NULL]);
            if($clearOldInteressentPWDResponse == false) {
                sendErrorNotificationMail('Für den Datensatz ' . $addressRecord->GGUID . ' konnte das NCINTERESSENTPWD nicht gelöscht werden.');
            }
        } else {
            sendErrorNotificationMail('Beim Passwort zurücksetzen konnte für Datensatz ' . $addressRecord->GGUID . ' kein Nutzername und Password Verknüpfung angelegt werden.');
        }
    }


    $resetPasswordData = new stdClass();
    $resetPasswordData->email = $email;
    $resetPasswordData->card_name = $user->card_name;

    DB::table('mycitycards_sessions')->where('id', $user->id)->delete();

    try {
        Mail::to($email)->send(new ResetPasswordSuccessCustomerMail($resetPasswordData));
    } catch (Exception $ex) {
        Log::error($ex->getMessage());
    }

    return response()->json( "Ihr Passwort wurde erfolgreich geändert. Sie können sich nun mit dem neuen Password einloggen.", 200 );
});



function updateInxmailUser($activateOrDeactivate, $userData) {

    if(!property_exists($userData, 'email') || empty($userData->email)) {
        Log::error("Bei updateInxmailUser wurde keine E-Mail angegeben");
        return false;
    }

    if(!property_exists($userData, 'inxmailListId') || empty($userData->inxmailListId)) {
        Log::error("Bei updateInxmailUser wurde keine inxmailListId angegeben");
        return false;
    }

    $user_attributes = [];
    if(property_exists($userData, 'firstName') && !empty($userData->firstName)) {
        $user_attributes['Vorname'] = $userData->firstName;
    }
    if(property_exists($userData, 'lastName') && !empty($userData->lastName)) {
        $user_attributes['Name'] = $userData->lastName;
    }
    if(property_exists($userData, 'gguid') && !empty($userData->gguid)) {
        $user_attributes['CASgW_GGUID'] = '0x' . $userData->gguid;
    }
    if(property_exists($userData, 'addressterm') && !empty($userData->addressterm)) {
        $user_attributes['Anrede'] = $userData->addressterm;
    }
    if(property_exists($userData, 'addressletter') && !empty($userData->addressletter)) {
        $user_attributes['Briefanrede'] = $userData->addressletter;
    }

    if($activateOrDeactivate == "activate") {
        $subscribeAction = 'subscriptions';
    } else if($activateOrDeactivate == "deactivate") {
        $subscribeAction = 'unsubscriptions';
    }


    $inxmailResponse = Http::withBasicAuth('CASgw_X14f37ca6b2-0a6b-465c-b4d7-a1a76de1c7c8', 'AIGYEJpGerYxUa5xF_FTWGM_irHfldiapqvPSTTie68WIhnBSMhtSgwrZpHX90PwPk_cclj3cYhctB6Mi5L6BkE')
        ->post('https://api.inxmail.com/trolleymaker/rest/v1/events/' . $subscribeAction, [
            'listId' => $userData->inxmailListId,
            'email' =>  trim($userData->email),
            'suppliedRemoteAddress' => $_SERVER["REMOTE_ADDR"],
            'source' => 'Webportal',
            'attributes' => $user_attributes
        ]);

    Log::debug('Inxmail Response: ' . print_r($inxmailResponse->body(), true));

    if($inxmailResponse->failed()) {
        Log::error('Fehler bei Newsletteranmeldung für E-Mail:' . print_r($userData->email, true) . ' , Fehler:' . print_r($inxmailResponse->body(), true));
        return false;
    }


    $inxmailResponseJson = json_decode($inxmailResponse);
    if($inxmailResponseJson != NULL && property_exists($inxmailResponseJson, 'result')) {
        if($inxmailResponseJson->result == 'VERIFIED_SUBSCRIPTION' || $inxmailResponseJson->result == 'VERIFIED_UNSUBSCRIPTION' || $inxmailResponseJson->result == 'PENDING_UNSUBSCRIPTION' || $inxmailResponseJson->result == 'PENDING_UNSUBSCRIPTION'
            || $inxmailResponseJson->result == 'PENDING_UNSUBSCRIPTION_DONE' || $inxmailResponseJson->result == 'MANUAL_UNSUBSCRIPTION' || $inxmailResponseJson->result == 'LIST_UNSUBSCRIBE_HEADER_UNSUBSCRIPTION'
            || $inxmailResponseJson->result == 'PENDING_SUBSCRIPTION' || $inxmailResponseJson->result == 'PENDING_SUBSCRIPTION_DONE' || $inxmailResponseJson->result == 'MANUAL_SUBSCRIPTION' || $inxmailResponseJson->result == 'DUPLICATE_SUBSCRIPTION') {
            return true;
        }
    }

    Log::error('Unbekannter Fehler bei Newsletteranmeldung für E-Mail: ' . print_r($userData->email, true) . ', Fehler: ' . print_r($inxmailResponse->body(), true));
    return false;
}


Route::post('/update-consents', function (Request $request) {

    $consent_data = handleUpdateCustomerConsents($request);

    if(isError($consent_data)) {
        return returnErrorObject($consent_data);
    }

    return response()->json( $consent_data, 200 );

})->middleware(['AuthenticateWithSession']);


function handleUpdateCustomerConsents($request) {

    if(!$request->has('marketingAdsConsent')) {
        return createErrorObject('Es wurde kein Marketing Ads Consent angegeben!', 'no_marketingAdsConsent', 400);
    }

    if(!$request->has('newsletterConsent')) {
        return createErrorObject('Es wurde kein Newsletter Consent angegeben!', 'no_newsletterConsent', 400);
    }

    $personal_data = getGwPersonalDataByGGUID($request->input('contact_person_gguid'));

    if(isError($personal_data)) {
        return $personal_data;
    }


    if($personal_data->NCNLANGEMELDET != $request->input('newsletterConsent')) {
        if($request->input('newsletterConsent') == true) {
            $activateOrDeactivate = 'activate';
        } else {
            $activateOrDeactivate = 'deactivate';
        }

        $userData = new stdClass();
        $userData->firstName = $personal_data->CHRISTIANNAME;
        $userData->lastName = $personal_data->NAME;
        $userData->email = $personal_data->MAILFIELDSTR3;
        $userData->gguid = $request->input('contact_person_gguid');
        $userData->region_name = $personal_data->NCREGION;
        $userData->card_name = $personal_data->NCORTDERANMELDUNG;
        if(!property_exists($personal_data, 'TITLE')) {
            $personal_data->TITLE = '';
        }

        $guessedSalutation = _guessSalutationFromGW($userData->firstName, $userData->lastName, $personal_data->GWGENDER, $personal_data->TITLE, $personal_data->COUNTRY3);

        $userData->addressterm = property_exists($personal_data, 'ADDRESSTERM') ? $personal_data->ADDRESSTERM : $guessedSalutation->addressterm;
        $userData->addressletter = property_exists($personal_data, 'ADDRESSLETTER') ? $personal_data->ADDRESSLETTER : $guessedSalutation->addressletter;

        $regionData = getRegionData($userData->region_name, $userData->card_name, ['acf.inxmail_verteilerlist_id']);

        if(isError($regionData) || !property_exists($regionData, 'acf') || !property_exists($regionData->acf, 'inxmail_verteilerlist_id')) {
            Log::error('Es ist ein Fehler bei der Newsletteranmeldung/-abmeldung aufgetreten, die Regionsdaten konnten nicht abgerufen werden');
            return createErrorObject('Es ist ein Fehler bei der Newsletteranmeldung/-abmeldung aufgetreten, die Regionsdaten konnten nicht abgerufen werden', 'no_region_data', 500);
        } else {
            $userData->inxmailListId = $regionData->acf->inxmail_verteilerlist_id;
        }

        if(!updateInxmailUser($activateOrDeactivate, $userData)) {
            return createErrorObject('Es ist ein Fehler bei der Newsletteranmeldung/-abmeldung aufgetreten', 'error_updating_newsletter' , 500);
        }
    }

    $fields = new stdClass();
    $fields->NCWERBUNGEINWILLIGUNG = $request->input('marketingAdsConsent');
    $fields->NCNLANGEMELDET = $request->input('newsletterConsent');

    if(!updateGwAddressData($request->input('contact_person_gguid'), $fields)) {
        return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500 );
    } else {
        $response_to_send = [
            'marketingAdsConsent' => $request->input('marketingAdsConsent'),
            'newsletterConsent' => $request->input('newsletterConsent')
        ];
        return $response_to_send;
    }
}


Route::get('/personal-data', function (Request $request) {

    $personal_data = handleGetCustomerPersonalData($request);
    if(isError($personal_data)) {
        return returnErrorObject($personal_data);
    }

    $cards = getCardsForCustomer($personal_data->GGUID);
    if(isError($cards)) {
        Log::error("no card_data for " . $personal_data->GGUID . ": " . print_r($cards, true));
        return returnNewErrorObject( 'Für Ihren Account wurden keine Karten gefunden. Bitte wenden Sie sich an den Support', 'no_cardIDs', 500);
    }

    if(count($cards) > 0) {
        $cardIDs = array_column($cards, 'KVWKARTENNUMMER');
    } else {
        $cardIDs = array();
    }


    $response_to_send = [
        'email' => $personal_data->MAILFIELDSTR3,
        'salutation' => $personal_data->ADDRESSTERM,
        'gender' => $personal_data->GWGENDER,
        'firstName'=> $personal_data->CHRISTIANNAME,
        'lastName'=> $personal_data->NAME,
        'street'=> $personal_data->STREET3,
        'zip'=> $personal_data->ZIP3,
        'city'=> $personal_data->TOWN3,
        'country'=> $personal_data->COUNTRY3,
        'phone' => $personal_data->PHONEFIELDSTR7,
        'birthdate'=> $personal_data->BIRTHDAY_ISO,
        'isCardActive' => $personal_data->NCKARTEAKTIV,
        'marketingAdsConsent' => $personal_data->NCWERBUNGEINWILLIGUNG,
        'newsletterConsent' => $personal_data->NCNLANGEMELDET,
        'cardIDs' => $cardIDs
    ];

    return response()->json( $response_to_send, 200 );

})->middleware(['AuthenticateWithSession']);


function handleGetCustomerPersonalData($request) {

    $personal_data = getGwPersonalDataByGGUID($request->input('contact_person_gguid'));

    if(isError($personal_data)) {
        return $personal_data;
    }

    if(property_exists($personal_data, 'BIRTHDAY') && $personal_data->BIRTHDAY != '' && strlen($personal_data->BIRTHDAY) > 0) {
        $personal_data->BIRTHDAY_FORMATTED_DE = gWDateToGermanDate($personal_data->BIRTHDAY);
        $personal_data->BIRTHDAY_ISO = convertDateWithFormatToISODateWithoutTime($personal_data->BIRTHDAY, 'Y-m-d\TH:i:s.v\Z');
    } else {
        $personal_data->BIRTHDAY = '';
        $personal_data->BIRTHDAY_ISO = '';
        $personal_data->BIRTHDAY_FORMATTED_DE = '';
    }

    if(!property_exists($personal_data, 'PHONEFIELDSTR7') || $personal_data->PHONEFIELDSTR7 == '' || strlen($personal_data->PHONEFIELDSTR7) <= 0) {
        $personal_data->PHONEFIELDSTR7 = '';
    }

    if(!property_exists($personal_data, 'ADDRESSTERM') || $personal_data->ADDRESSTERM == '' || strlen($personal_data->ADDRESSTERM) <= 0) {
        $personal_data->ADDRESSTERM = '';
    }

    if(!property_exists($personal_data, 'GWGENDER') || $personal_data->GWGENDER == '' || strlen($personal_data->GWGENDER) <= 0) {
        $personal_data->GWGENDER = '';
    }

    if(!property_exists($personal_data, 'STREET3') || $personal_data->STREET3 == '' || strlen($personal_data->STREET3) <= 0) {
        $personal_data->STREET3 = '';
    }

    if(!property_exists($personal_data, 'ZIP3') || $personal_data->ZIP3 == '' || strlen($personal_data->ZIP3) <= 0) {
        $personal_data->ZIP3 = '';
    }

    if(!property_exists($personal_data, 'TOWN3') || $personal_data->TOWN3 == '' || strlen($personal_data->TOWN3) <= 0) {
        $personal_data->TOWN3 = '';
    }

    if(!property_exists($personal_data, 'COUNTRY3') || $personal_data->COUNTRY3 == '' || strlen($personal_data->COUNTRY3) <= 0) {
        $personal_data->COUNTRY3 = '';
    }

    return $personal_data;
}


Route::post('/partner-personal-data', function (Request $request) {

    foreach(array('companyName', 'companyStreet', 'companyZip', 'companyCity', 'companyCountry', 'contactPersonGender', 'contactPersonFirstName', 'contactPersonLastName',
                  'contactPersonEmail', 'companyREName', 'companyREStreet', 'companyREZip', 'companyRECity', 'companyRECountry', 'companyREEmail', 'ceoName', 'ceoPhone',
                  'sepaAccountHolder', 'sepaIBAN', 'sepaBIC', 'sepaBankName' ) as $input) {
        if($request->input($input) == NULL || $request->input($input) == '') {
            return returnNewErrorObject('Es wurden nicht alle erforderlichen Felder ausgefüllt!' . $input, 'missing_fields', 400);
        }
    }

    if(!_isPartnerAdmin($request)) {
        return returnNewErrorObject('Sie haben nicht die benötigte Berechtigung, um Partnerdaten zu bearbeiten. Bitte kontaktieren Sie den Support.', 'no_permission', 400);
    }

    $headquarter_data = getGwPersonalDataByGGUID($request->input('company_gguid'));
    if(!property_exists($headquarter_data, 'GGUID')) {
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Die Firma wurde nicht gefunden. Bitte kontaktieren Sie den Support.', 'no_company', 400);
    }

    $personal_data = getGwPersonalDataByGGUID($request->input('contact_person_gguid'));
    if(!property_exists($personal_data, 'GGUID')) {
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Der Ansprechpartner wurde nicht gefunden. Bitte kontaktieren Sie den Support.', 'no_contact_person', 400);
    }

    if(strtolower($headquarter_data->TYPSTANDORT) != 'zentrale') {
        return returnNewErrorObject('Sie haben nicht die nötige Berechtigung, um die Daten der Firma zu ändern. Bitte wenden Sie sich an den Support.', 'no_permission', 401);
    }

    $requestBranches = $request->input('branches');

    //update contact person
    $contactPersonFieldsToUpdate = new stdClass();
    $contactPersonFieldsToUpdate->GWGENDER = $request->input('contactPersonGender');
    $contactPersonFieldsToUpdate->CHRISTIANNAME = $request->input('contactPersonFirstName');
    $contactPersonFieldsToUpdate->NAME = $request->input('contactPersonLastName');
    if(!updateGwAddressData($request->input('contact_person_gguid'), $contactPersonFieldsToUpdate)) {
        Log::Error('Bei /partner-personal-data ist ein Fehler aufgetreten. Der Ansprechpartner konnte nicht geupdatet werden.');
        return returnNewErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
    }

    $handlingAtPOS = implode(', ', array_filter([
        $request->input('handlingAtPOSPC') === true ? 'PC / PC-Kasse' : null,
        $request->input('handlingAtPOSOnlineshop') === true ? 'Onlineshop' : null,
        $request->input('handlingAtPOSSmartphone') === true ? 'Smartphone / Tablet' : null,
        $request->input('handlingAtPOSEcDevice') === true ? 'EC Terminal' : null,
        $request->input('handlingAtPOSCashpoint') === true ? 'Handelskassenanbindung' : null,
        ]));


    //update headquarter
    $headquarterFieldsToUpdate = new stdClass();
    $headquarterFieldsToUpdate->COMPNAME = $request->input('companyName');
    $headquarterFieldsToUpdate->GWADDITIONALINFO1 = $request->input('companyAddressAdditional');
    $headquarterFieldsToUpdate->STREET1 = $request->input('companyStreet');
    $headquarterFieldsToUpdate->TOWN1 = $request->input('companyCity');
    $headquarterFieldsToUpdate->ZIP1 = $request->input('companyZip');
    $headquarterFieldsToUpdate->COUNTRY1 = $request->input('companyCountry');
    $headquarterFieldsToUpdate->MAILFIELDSTR4 = $request->input('companyEmail');
    $headquarterFieldsToUpdate->MAILFIELDSTR5 = $request->input('companyEmailHeadquarter');
    $headquarterFieldsToUpdate->NCREFIRMA = $request->input('companyREName');
    $headquarterFieldsToUpdate->NCREZIP = $request->input('companyREZip');
    $headquarterFieldsToUpdate->NCRESTREET = $request->input('companyREStreet');
    $headquarterFieldsToUpdate->NCREORT = $request->input('companyRECity');
    $headquarterFieldsToUpdate->TMRELAND = $request->input('companyRECountry');
    $headquarterFieldsToUpdate->TMMAILRECHNUNG = $request->input('companyREEmail');
    $headquarterFieldsToUpdate->TMFIRMENINHABER = $request->input('ceoName');
    $headquarterFieldsToUpdate->PHONEFIELDSTR9 = $request->input('ceoPhone');

    /*
    if($headquarter_data->NCREGION == 'Lahr') {
        $disagioFromRequest = $request->input('disagio');
        if(strlen($disagioFromRequest) < 6) {
            $disagioFromRequest = getLongDisagioText($disagioFromRequest);
        }
    } else {
        $disagioFromRequest = "2% - keine Teilnahmegebühr";
    }

    $headquarterFieldsToUpdate->TMDISAGIOMODELLPARTNER = $disagioFromRequest; */
    $headquarterFieldsToUpdate->TMHANDLINGAMPOS = $handlingAtPOS;
    $headquarterFieldsToUpdate->TMBETRIEBSSYSTEM = $request->input('smartphoneOS');
    $headquarterFieldsToUpdate->TMHERSTELLEREC = $request->input('ecManufacturer');
    $headquarterFieldsToUpdate->TMTYPEC = $request->input('ecType');
    $headquarterFieldsToUpdate->TMECTERMINALID = $request->input('ecTerminalID');
    $headquarterFieldsToUpdate->TMECTERMINALSERIENNR = $request->input('ecSerialNumber');
    $headquarterFieldsToUpdate->TMHANDELKASSENANBINDUNG = $request->input('ecCashpointIntegration');
    $headquarterFieldsToUpdate->TMHANDELSKASSENANBIETER = $request->input('ecCashpointIntegrationManufacturer');
    $headquarterFieldsToUpdate->TMHANDELSKASSENANBIETER = $request->input('cashpointManufacturer');
    if(property_exists($headquarter_data, 'NCARTABRECHNUNG') && $headquarter_data->NCARTABRECHNUNG != '') {
        //dont allow updatig payment method, so use existing payment method and not from request
        $headquarterFieldsToUpdate->NCARTABRECHNUNG = $headquarter_data->NCARTABRECHNUNG;
    } else {
        $headquarterFieldsToUpdate->NCARTABRECHNUNG = $request->input('paymentMethod');
    }
    $headquarterFieldsToUpdate->GWBIC = strtoupper($request->input('sepaBIC'));
    $headquarterFieldsToUpdate->GWIBAN = $request->input('sepaIBAN');
    $headquarterFieldsToUpdate->FINANCIALINSTITUTE = $request->input('sepaBankName');
    $headquarterFieldsToUpdate->BANKACCOUNTHOLDER = $request->input('sepaAccountHolder');


    $headquarterRequestData = array_shift($requestBranches);
    $headquarterRequestData = json_decode(json_encode($headquarterRequestData), FALSE);

    $headquarterFieldsToUpdate->TMINTERNEBEZEICHNUNG = property_exists($headquarterRequestData, 'companyNameIntern') ? $headquarterRequestData->companyNameIntern : '';
    $headquarterFieldsToUpdate->COMPNAME2 = $headquarterRequestData->companyName;
    $headquarterFieldsToUpdate->STREET2 = $headquarterRequestData->companyStreet;
    $headquarterFieldsToUpdate->TOWN2 = $headquarterRequestData->companyCity;
    $headquarterFieldsToUpdate->COUNTRY2 = $headquarterRequestData->companyCountry;
    $headquarterFieldsToUpdate->ZIP2 = $headquarterRequestData->companyZip;
    $headquarterFieldsToUpdate->WWWFIELDSTR1 = property_exists($headquarterRequestData, 'companyWebsite') ? $headquarterRequestData->companyWebsite : '';
    $headquarterFieldsToUpdate->TMMAILVEROEFFENTLICHUNG = property_exists($headquarterRequestData, 'companyEmail') ? $headquarterRequestData->companyEmail : '';
    $headquarterFieldsToUpdate->TMPHONEVEROEFFENTLICHUNG = property_exists($headquarterRequestData, 'companyPhone') ? $headquarterRequestData->companyPhone : '';
    $headquarterFieldsToUpdate->CATEGORY = implode(",", $headquarterRequestData->companyCategories);
    $headquarterFieldsToUpdate->TMTERMINVEREINBARUNG = property_exists($headquarterRequestData, 'companyOpenHoursOnlyByArrangement') ? $headquarterRequestData->companyOpenHoursOnlyByArrangement : false;
    $headquarterFieldsToUpdate->TMPARTNERHATGESCHLOSSENMO = property_exists($headquarterRequestData, 'isClosedOnMonday') ? $headquarterRequestData->isClosedOnMonday : false;
    $headquarterFieldsToUpdate->TMPARTNERHATGESCHLOSSENDI = property_exists($headquarterRequestData, 'isClosedOnTuesday') ? $headquarterRequestData->isClosedOnTuesday : false;
    $headquarterFieldsToUpdate->TMPARTNERHATGESCHLOSSENMI = property_exists($headquarterRequestData, 'isClosedOnWednesday') ? $headquarterRequestData->isClosedOnWednesday : false;
    $headquarterFieldsToUpdate->TMPARTNERHATGESCHLOSSENDO = property_exists($headquarterRequestData, 'isClosedOnThursday') ? $headquarterRequestData->isClosedOnThursday : false;
    $headquarterFieldsToUpdate->TMPARTNERHATGESCHLOSSENFR = property_exists($headquarterRequestData, 'isClosedOnFriday') ? $headquarterRequestData->isClosedOnFriday : false;
    $headquarterFieldsToUpdate->TMPARTNERHATGESCHLOSSENSA = property_exists($headquarterRequestData, 'isClosedOnSaturday') ? $headquarterRequestData->isClosedOnSaturday : false;
    $headquarterFieldsToUpdate->TMPARTNERHATGESCHLOSSENSO = property_exists($headquarterRequestData, 'isClosedOnSunday') ? $headquarterRequestData->isClosedOnSunday : false;
    $headquarterFieldsToUpdate->TMOEFFZEITMONTAG1VON = property_exists($headquarterRequestData, 'companyOpenHoursMondayFrom1') && !is_bool($headquarterRequestData->companyOpenHoursMondayFrom1) ? $headquarterRequestData->companyOpenHoursMondayFrom1 : NULL;
    $headquarterFieldsToUpdate->TMOEFFZEITMONTAG2VON = property_exists($headquarterRequestData, 'companyOpenHoursMondayFrom2') && !is_bool($headquarterRequestData->companyOpenHoursMondayFrom2) ? $headquarterRequestData->companyOpenHoursMondayFrom2 : NULL;
    $headquarterFieldsToUpdate->TMOEFFZEITMONTAG1BIS = property_exists($headquarterRequestData, 'companyOpenHoursMondayTo1') && !is_bool($headquarterRequestData->companyOpenHoursMondayTo1) ? $headquarterRequestData->companyOpenHoursMondayTo1 : NULL;
    $headquarterFieldsToUpdate->TMOEFFZEITMONTAG2BIS = property_exists($headquarterRequestData, 'companyOpenHoursMondayTo2') && !is_bool($headquarterRequestData->companyOpenHoursMondayTo2) ? $headquarterRequestData->companyOpenHoursMondayTo2 : NULL;
    $headquarterFieldsToUpdate->TMOEFFZEITDIENSTAG1VON = property_exists($headquarterRequestData, 'companyOpenHoursTuesdayFrom1') && !is_bool($headquarterRequestData->companyOpenHoursTuesdayFrom1) ? $headquarterRequestData->companyOpenHoursTuesdayFrom1 : NULL;
    $headquarterFieldsToUpdate->TMOEFFZEITDIENSTAG2VON = property_exists($headquarterRequestData, 'companyOpenHoursTuesdayFrom2') && !is_bool($headquarterRequestData->companyOpenHoursTuesdayFrom2) ? $headquarterRequestData->companyOpenHoursTuesdayFrom2 : NULL;
    $headquarterFieldsToUpdate->TMOEFFZEITDIENSTAG1BIS = property_exists($headquarterRequestData, 'companyOpenHoursTuesdayTo1') && !is_bool($headquarterRequestData->companyOpenHoursTuesdayTo1) ? $headquarterRequestData->companyOpenHoursTuesdayTo1 : NULL;
    $headquarterFieldsToUpdate->TMOEFFZEITDIENSTAG2BIS = property_exists($headquarterRequestData, 'companyOpenHoursTuesdayTo2') && !is_bool($headquarterRequestData->companyOpenHoursTuesdayTo2) ? $headquarterRequestData->companyOpenHoursTuesdayTo2 : NULL;
    $headquarterFieldsToUpdate->TMOEFFZEITMITTWOCH1VON = property_exists($headquarterRequestData, 'companyOpenHoursWednesdayFrom1') && !is_bool($headquarterRequestData->companyOpenHoursWednesdayFrom1) ? $headquarterRequestData->companyOpenHoursWednesdayFrom1 : NULL;
    $headquarterFieldsToUpdate->TMOEFFZEITMITTWOCH2VON = property_exists($headquarterRequestData, 'companyOpenHoursWednesdayFrom2') && !is_bool($headquarterRequestData->companyOpenHoursWednesdayFrom2) ? $headquarterRequestData->companyOpenHoursWednesdayFrom2 : NULL;
    $headquarterFieldsToUpdate->TMOEFFZEITMITTWOCH1BIS = property_exists($headquarterRequestData, 'companyOpenHoursWednesdayTo1') && !is_bool($headquarterRequestData->companyOpenHoursWednesdayTo1) ? $headquarterRequestData->companyOpenHoursWednesdayTo1 : NULL;
    $headquarterFieldsToUpdate->TMOEFFZEITMITTWOCH2BIS = property_exists($headquarterRequestData, 'companyOpenHoursWednesdayTo2') && !is_bool($headquarterRequestData->companyOpenHoursWednesdayTo2) ? $headquarterRequestData->companyOpenHoursWednesdayTo2 : NULL;
    $headquarterFieldsToUpdate->TMOEFFZEITDONNERSTAG1VON = property_exists($headquarterRequestData, 'companyOpenHoursThursdayFrom1') && !is_bool($headquarterRequestData->companyOpenHoursThursdayFrom1) ? $headquarterRequestData->companyOpenHoursThursdayFrom1 : NULL;
    $headquarterFieldsToUpdate->TMOEFFZEITDONNERSTAG2VON = property_exists($headquarterRequestData, 'companyOpenHoursThursdayFrom2') && !is_bool($headquarterRequestData->companyOpenHoursThursdayFrom2) ? $headquarterRequestData->companyOpenHoursThursdayFrom2 : NULL;
    $headquarterFieldsToUpdate->TMOEFFZEITDONNERSTAG1BIS = property_exists($headquarterRequestData, 'companyOpenHoursThursdayTo1') && !is_bool($headquarterRequestData->companyOpenHoursThursdayTo1) ? $headquarterRequestData->companyOpenHoursThursdayTo1 : NULL;
    $headquarterFieldsToUpdate->TMOEFFZEITDONNERSTAG2BIS = property_exists($headquarterRequestData, 'companyOpenHoursThursdayTo2') && !is_bool($headquarterRequestData->companyOpenHoursThursdayTo2) ? $headquarterRequestData->companyOpenHoursThursdayTo2 : NULL;
    $headquarterFieldsToUpdate->TMOEFFZEITFREITAG1VON = property_exists($headquarterRequestData, 'companyOpenHoursFridayFrom1') && !is_bool($headquarterRequestData->companyOpenHoursFridayFrom1) ? $headquarterRequestData->companyOpenHoursFridayFrom1 : NULL;
    $headquarterFieldsToUpdate->TMOEFFZEITFREITAG2VON = property_exists($headquarterRequestData, 'companyOpenHoursFridayFrom2') && !is_bool($headquarterRequestData->companyOpenHoursFridayFrom2) ? $headquarterRequestData->companyOpenHoursFridayFrom2 : NULL;
    $headquarterFieldsToUpdate->TMOEFFZEITFREITAG1BIS = property_exists($headquarterRequestData, 'companyOpenHoursFridayTo1') && !is_bool($headquarterRequestData->companyOpenHoursFridayTo1) ? $headquarterRequestData->companyOpenHoursFridayTo1 : NULL;
    $headquarterFieldsToUpdate->TMOEFFZEITFREITAG2BIS = property_exists($headquarterRequestData, 'companyOpenHoursFridayTo2') && !is_bool($headquarterRequestData->companyOpenHoursFridayTo2) ? $headquarterRequestData->companyOpenHoursFridayTo2 : NULL;
    $headquarterFieldsToUpdate->TMOEFFZEITSAMSTAG1VON = property_exists($headquarterRequestData, 'companyOpenHoursSaturdayFrom1') && !is_bool($headquarterRequestData->companyOpenHoursSaturdayFrom1) ? $headquarterRequestData->companyOpenHoursSaturdayFrom1 : NULL;
    $headquarterFieldsToUpdate->TMOEFFZEITSAMSTAG2VON = property_exists($headquarterRequestData, 'companyOpenHoursSaturdayFrom2') && !is_bool($headquarterRequestData->companyOpenHoursSaturdayFrom2) ? $headquarterRequestData->companyOpenHoursSaturdayFrom2 : NULL;
    $headquarterFieldsToUpdate->TMOEFFZEITSAMSTAG1BIS = property_exists($headquarterRequestData, 'companyOpenHoursSaturdayTo1') && !is_bool($headquarterRequestData->companyOpenHoursSaturdayTo1) ? $headquarterRequestData->companyOpenHoursSaturdayTo1 : NULL;
    $headquarterFieldsToUpdate->TMOEFFZEITSAMSTAG2BIS = property_exists($headquarterRequestData, 'companyOpenHoursSaturdayTo2') && !is_bool($headquarterRequestData->companyOpenHoursSaturdayTo2)? $headquarterRequestData->companyOpenHoursSaturdayTo2 : NULL;
    $headquarterFieldsToUpdate->TMOEFFZEITSONNTAG1VON = property_exists($headquarterRequestData, 'companyOpenHoursSundayFrom1') && !is_bool($headquarterRequestData->companyOpenHoursSundayFrom1) ? $headquarterRequestData->companyOpenHoursSundayFrom1 : NULL;
    $headquarterFieldsToUpdate->TMOEFFZEITSONNTAG2VON = property_exists($headquarterRequestData, 'companyOpenHoursSundayFrom2') && !is_bool($headquarterRequestData->companyOpenHoursSundayFrom2) ? $headquarterRequestData->companyOpenHoursSundayFrom2 : NULL;
    $headquarterFieldsToUpdate->TMOEFFZEITSONNTAG1BIS = property_exists($headquarterRequestData, 'companyOpenHoursSundayTo1') && !is_bool($headquarterRequestData->companyOpenHoursSundayTo1) ? $headquarterRequestData->companyOpenHoursSundayTo1 : NULL;
    $headquarterFieldsToUpdate->TMOEFFZEITSONNTAG2BIS = property_exists($headquarterRequestData, 'companyOpenHoursSundayTo2') && !is_bool($headquarterRequestData->companyOpenHoursSundayTo2) ? $headquarterRequestData->companyOpenHoursSundayTo2 : NULL;
    $headquarterFieldsToUpdate->TMINFOOEFFNUNGSZEIT = property_exists($headquarterRequestData, 'companyOpenHoursAdditionalInfo') ? $headquarterRequestData->companyOpenHoursAdditionalInfo : NULL;
    if(!updateGwAddressData($request->input('company_gguid'), $headquarterFieldsToUpdate)) {
        Log::Error('Bei /partner-personal-data ist ein Fehler aufgetreten. Die Zentrale konnte nicht geupdatet werden.');
        return returnNewErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
    }

    $vmPayment = 'SEPA_DirectDebit';
    if($headquarterFieldsToUpdate->NCARTABRECHNUNG == 'Bankeinzug') {
        $vmPayment = 'SEPA_DirectDebit';
    } else if($headquarterFieldsToUpdate->NCARTABRECHNUNG == 'Rechnung') {
        $vmPayment = 'Invoice';
    }
    $vmHeadquarterFieldsToSet = new stdClass();
    $vmHeadquarterFieldsToSet->companyName = $headquarterFieldsToUpdate->COMPNAME;
    $vmHeadquarterFieldsToSet->companyID = intval($headquarter_data->NCFIRMENID);
    $vmHeadquarterFieldsToSet->active = '1';
    $vmHeadquarterFieldsToSet->internalID = $headquarter_data->NCINTERNEID;
    $vmHeadquarterFieldsToSet->phoneNumber = $headquarterRequestData->companyPhone;
    $vmHeadquarterFieldsToSet->street = $headquarterFieldsToUpdate->STREET1;
    $vmHeadquarterFieldsToSet->zip = $headquarterFieldsToUpdate->ZIP1;
    $vmHeadquarterFieldsToSet->city = $headquarterFieldsToUpdate->TOWN1;
    $vmHeadquarterFieldsToSet->country = $headquarterFieldsToUpdate->COUNTRY1;
    $vmHeadquarterFieldsToSet->companyEmail = $headquarterFieldsToUpdate->MAILFIELDSTR4;
    $vmHeadquarterFieldsToSet->bankName = $headquarterFieldsToUpdate->FINANCIALINSTITUTE;
    $vmHeadquarterFieldsToSet->iban = $headquarterFieldsToUpdate->GWIBAN;
    $vmHeadquarterFieldsToSet->bic = strtoupper($headquarterFieldsToUpdate->GWBIC);
    $vmHeadquarterFieldsToSet->companyNameOnInvoice = $headquarterFieldsToUpdate->NCREFIRMA;
    $vmHeadquarterFieldsToSet->companyContactPersonOnInvoice = $headquarterFieldsToUpdate->TMFIRMENINHABER;
    $vmHeadquarterFieldsToSet->invoiceStreet = $headquarterFieldsToUpdate->NCRESTREET;
    $vmHeadquarterFieldsToSet->invoiceZIP = $headquarterFieldsToUpdate->NCREZIP;
    $vmHeadquarterFieldsToSet->invoiceCity = $headquarterFieldsToUpdate->NCREORT;
    $vmHeadquarterFieldsToSet->invoiceMail = $headquarterFieldsToUpdate->TMMAILRECHNUNG;
    $vmHeadquarterFieldsToSet->payment = $vmPayment;

    $updateHeadquarterPartnerInValueMaster = addOrModifyPartnerInValueMaster($vmHeadquarterFieldsToSet);

    if($updateHeadquarterPartnerInValueMaster->failed() || $updateHeadquarterPartnerInValueMaster == NULL) {
        Log::Error('Registrierung der neuen Filiale ist im ValueMaster fehlgeschlagen: ' . $updateHeadquarterPartnerInValueMaster->body());
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Partner-Account konnte nicht aktualisiert werden. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
    }

    if($updateHeadquarterPartnerInValueMaster && $updateHeadquarterPartnerInValueMaster != NULL) {
        $headquarterPartnerDataFromValueMaster = json_decode($updateHeadquarterPartnerInValueMaster)->d;

        if($headquarterPartnerDataFromValueMaster && $headquarterPartnerDataFromValueMaster != NULL) {
            if(!property_exists($headquarterPartnerDataFromValueMaster, 'status') || strtolower($headquarterPartnerDataFromValueMaster->status) != 'ok' || !empty($headquarterPartnerDataFromValueMaster->error)) {
                Log::Error('Updaten der Zentrale ist im ValueMaster fehlgeschlagen: ' . $updateHeadquarterPartnerInValueMaster->body());
                return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Partner-Account konnte nicht aktualisiert werden. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
            }
            if(!property_exists($headquarterPartnerDataFromValueMaster, 'CompanyID')) {
                Log::Error('Updaten der Zentrale ist im ValueMaster fehlgeschlagen: ' . $updateHeadquarterPartnerInValueMaster->body());
                return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Partner-Account konnte nicht aktualisiert werden. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
            }
        } else {
            Log::Error('Updaten der Zentrale ist im ValueMaster fehlgeschlagen: ' . $updateHeadquarterPartnerInValueMaster->body());
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Partner-Account konnte nicht aktualisiert werden. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
        }
    } else {
        Log::Error('Updaten der Zentrale ist im ValueMaster fehlgeschlagen: ' . $updateHeadquarterPartnerInValueMaster->body());
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Partner-Account konnte nicht aktualisiert werden. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
    }

    //get branches
    $gwGetBranches = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->get(env('GW_API_BASE') . '/type/address/full?linked-to=' . $headquarter_data->GGUID . '&linked-to-type=ADDRESS&linked-to-attributes=FILIALEZENTRALE&order-by=INSERTTIMESTAMP');

    if($gwGetBranches->failed()) {
        $error_obj = new stdClass();
        if($gwGetBranches->status() == 503) {
            return returnNewErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error("Fehler beim Abrufen von gwGetBranches: " . print_r($gwGetBranches->body(), true));
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
        }
        return $error_obj;
    }

    $gwOriginLinkedBranches = json_decode($gwGetBranches);


    $amountOfOriginBranches = count($gwOriginLinkedBranches);
    $amountOfRequestBranches = count($requestBranches);

    if($amountOfOriginBranches != 0 || $amountOfRequestBranches != 0) {
        $tempGwOriginLinkedBranches = [];
        foreach ($gwOriginLinkedBranches as $tempGwOriginLinkedBranch) {
            $tempGwOriginLinkedBranches[] = $tempGwOriginLinkedBranch->fields;
        }

        $gwOriginLinkedBranches = $tempGwOriginLinkedBranches;


        $i = 0;

        foreach ($requestBranches as $requestBranchTemp) {

            $requestBranch = json_decode(json_encode($requestBranchTemp), FALSE);

            $tempBranch = new stdClass();

            $tempBranch->TMINTERNEBEZEICHNUNG = property_exists($requestBranch, 'companyNameIntern') ? $requestBranch->companyNameIntern : '';
            $tempBranch->COMPNAME = $requestBranch->companyName;
            $tempBranch->COMPNAME2 = $requestBranch->companyName;
            $tempBranch->STREET2 = $requestBranch->companyStreet;
            $tempBranch->TOWN2 = $requestBranch->companyCity;
            $tempBranch->COUNTRY2 = $requestBranch->companyCountry;
            $tempBranch->ZIP2 = $requestBranch->companyZip;
            $tempBranch->WWWFIELDSTR1 = property_exists($requestBranch, 'companyWebsite') ? $requestBranch->companyWebsite : '';
            $tempBranch->TMMAILVEROEFFENTLICHUNG = property_exists($requestBranch, 'companyEmail') ? $requestBranch->companyEmail : '';
            $tempBranch->TMPHONEVEROEFFENTLICHUNG = property_exists($requestBranch, 'companyPhone') ? strval($requestBranch->companyPhone) : '';

            $tempBranch->CATEGORY = implode(",", $requestBranch->companyCategories);

            $tempBranch->TMTERMINVEREINBARUNG = property_exists($requestBranch, 'companyOpenHoursOnlyByArrangement') ? $requestBranch->companyOpenHoursOnlyByArrangement : false;
            $tempBranch->TMPARTNERHATGESCHLOSSENMO = property_exists($requestBranch, 'isClosedOnMonday') ? $requestBranch->isClosedOnMonday : false;
            $tempBranch->TMPARTNERHATGESCHLOSSENDI = property_exists($requestBranch, 'isClosedOnTuesday') ? $requestBranch->isClosedOnTuesday : false;
            $tempBranch->TMPARTNERHATGESCHLOSSENMI = property_exists($requestBranch, 'isClosedOnWednesday') ? $requestBranch->isClosedOnWednesday : false;
            $tempBranch->TMPARTNERHATGESCHLOSSENDO = property_exists($requestBranch, 'isClosedOnThursday') ? $requestBranch->isClosedOnThursday : false;
            $tempBranch->TMPARTNERHATGESCHLOSSENFR = property_exists($requestBranch, 'isClosedOnFriday') ? $requestBranch->isClosedOnFriday : false;
            $tempBranch->TMPARTNERHATGESCHLOSSENSA = property_exists($requestBranch, 'isClosedOnSaturday') ? $requestBranch->isClosedOnSaturday : false;
            $tempBranch->TMPARTNERHATGESCHLOSSENSO = property_exists($requestBranch, 'isClosedOnSunday') ? $requestBranch->isClosedOnSunday : false;
            $tempBranch->TMOEFFZEITMONTAG1VON = property_exists($requestBranch, 'companyOpenHoursMondayFrom1') ? $requestBranch->companyOpenHoursMondayFrom1 : NULL;
            $tempBranch->TMOEFFZEITMONTAG2VON = property_exists($requestBranch, 'companyOpenHoursMondayFrom2') ? $requestBranch->companyOpenHoursMondayFrom2 : NULL;
            $tempBranch->TMOEFFZEITMONTAG1BIS = property_exists($requestBranch, 'companyOpenHoursMondayTo1') ? $requestBranch->companyOpenHoursMondayTo1 : NULL;
            $tempBranch->TMOEFFZEITMONTAG2BIS = property_exists($requestBranch, 'companyOpenHoursMondayTo2') ? $requestBranch->companyOpenHoursMondayTo2 : NULL;
            $tempBranch->TMOEFFZEITDIENSTAG1VON = property_exists($requestBranch, 'companyOpenHoursTuesdayFrom1') ? $requestBranch->companyOpenHoursTuesdayFrom1 : NULL;
            $tempBranch->TMOEFFZEITDIENSTAG2VON = property_exists($requestBranch, 'companyOpenHoursTuesdayFrom2') ? $requestBranch->companyOpenHoursTuesdayFrom2 : NULL;
            $tempBranch->TMOEFFZEITDIENSTAG1BIS = property_exists($requestBranch, 'companyOpenHoursTuesdayTo1') ? $requestBranch->companyOpenHoursTuesdayTo1 : NULL;
            $tempBranch->TMOEFFZEITDIENSTAG2BIS = property_exists($requestBranch, 'companyOpenHoursTuesdayTo2') ? $requestBranch->companyOpenHoursTuesdayTo2 : NULL;
            $tempBranch->TMOEFFZEITMITTWOCH1VON = property_exists($requestBranch, 'companyOpenHoursWednesdayFrom1') ? $requestBranch->companyOpenHoursWednesdayFrom1 : NULL;
            $tempBranch->TMOEFFZEITMITTWOCH2VON = property_exists($requestBranch, 'companyOpenHoursWednesdayFrom2') ? $requestBranch->companyOpenHoursWednesdayFrom2 : NULL;
            $tempBranch->TMOEFFZEITMITTWOCH1BIS = property_exists($requestBranch, 'companyOpenHoursWednesdayTo1') ? $requestBranch->companyOpenHoursWednesdayTo1 : NULL;
            $tempBranch->TMOEFFZEITMITTWOCH2BIS = property_exists($requestBranch, 'companyOpenHoursWednesdayTo2') ? $requestBranch->companyOpenHoursWednesdayTo2 : NULL;
            $tempBranch->TMOEFFZEITDONNERSTAG1VON = property_exists($requestBranch, 'companyOpenHoursThursdayFrom1') ? $requestBranch->companyOpenHoursThursdayFrom1 : NULL;
            $tempBranch->TMOEFFZEITDONNERSTAG2VON = property_exists($requestBranch, 'companyOpenHoursThursdayFrom2') ? $requestBranch->companyOpenHoursThursdayFrom2 : NULL;
            $tempBranch->TMOEFFZEITDONNERSTAG1BIS = property_exists($requestBranch, 'companyOpenHoursThursdayTo1') ? $requestBranch->companyOpenHoursThursdayTo1 : NULL;
            $tempBranch->TMOEFFZEITDONNERSTAG2BIS = property_exists($requestBranch, 'companyOpenHoursThursdayTo2') ? $requestBranch->companyOpenHoursThursdayTo2 : NULL;
            $tempBranch->TMOEFFZEITFREITAG1VON = property_exists($requestBranch, 'companyOpenHoursFridayFrom1') ? $requestBranch->companyOpenHoursFridayFrom1 : NULL;
            $tempBranch->TMOEFFZEITFREITAG2VON = property_exists($requestBranch, 'companyOpenHoursFridayFrom2') ? $requestBranch->companyOpenHoursFridayFrom2 : NULL;
            $tempBranch->TMOEFFZEITFREITAG1BIS = property_exists($requestBranch, 'companyOpenHoursFridayTo1') ? $requestBranch->companyOpenHoursFridayTo1 : NULL;
            $tempBranch->TMOEFFZEITFREITAG2BIS = property_exists($requestBranch, 'companyOpenHoursFridayTo2') ? $requestBranch->companyOpenHoursFridayTo2 : NULL;
            $tempBranch->TMOEFFZEITSAMSTAG1VON = property_exists($requestBranch, 'companyOpenHoursSaturdayFrom1') ? $requestBranch->companyOpenHoursSaturdayFrom1 : NULL;
            $tempBranch->TMOEFFZEITSAMSTAG2VON = property_exists($requestBranch, 'companyOpenHoursSaturdayFrom2') ? $requestBranch->companyOpenHoursSaturdayFrom2 : NULL;
            $tempBranch->TMOEFFZEITSAMSTAG1BIS = property_exists($requestBranch, 'companyOpenHoursSaturdayTo1') ? $requestBranch->companyOpenHoursSaturdayTo1 : NULL;
            $tempBranch->TMOEFFZEITSAMSTAG2BIS = property_exists($requestBranch, 'companyOpenHoursSaturdayTo2') ? $requestBranch->companyOpenHoursSaturdayTo2 : NULL;
            $tempBranch->TMOEFFZEITSONNTAG1VON = property_exists($requestBranch, 'companyOpenHoursSundayFrom1') ? $requestBranch->companyOpenHoursSundayFrom1 : NULL;
            $tempBranch->TMOEFFZEITSONNTAG2VON = property_exists($requestBranch, 'companyOpenHoursSundayFrom2') ? $requestBranch->companyOpenHoursSundayFrom2 : NULL;
            $tempBranch->TMOEFFZEITSONNTAG1BIS = property_exists($requestBranch, 'companyOpenHoursSundayTo1') ? $requestBranch->companyOpenHoursSundayTo1 : NULL;
            $tempBranch->TMOEFFZEITSONNTAG2BIS = property_exists($requestBranch, 'companyOpenHoursSundayTo2') ? $requestBranch->companyOpenHoursSundayTo2 : NULL;
            $tempBranch->TMINFOOEFFNUNGSZEIT = property_exists($requestBranch, 'companyOpenHoursAdditionalInfo') ? $requestBranch->companyOpenHoursAdditionalInfo : NULL;


            $vmPartnerBranchFieldsToSet = new stdClass();
            $vmPartnerBranchFieldsToSet->companyName = $tempBranch->COMPNAME2;
            $vmPartnerBranchFieldsToSet->active = '1';
            $vmPartnerBranchFieldsToSet->internalID = $headquarter_data->NCINTERNEID;
            $vmPartnerBranchFieldsToSet->phoneNumber = $tempBranch->TMPHONEVEROEFFENTLICHUNG;
            $vmPartnerBranchFieldsToSet->street = $tempBranch->STREET2;
            $vmPartnerBranchFieldsToSet->zip = $tempBranch->ZIP2;
            $vmPartnerBranchFieldsToSet->city = $tempBranch->TOWN2;
            $vmPartnerBranchFieldsToSet->country = $tempBranch->COUNTRY2;
            $vmPartnerBranchFieldsToSet->companyEmail = $requestBranch->branchUsers[0]->contactPersonEmail;
            $vmPartnerBranchFieldsToSet->bankName = $headquarterFieldsToUpdate->FINANCIALINSTITUTE;
            $vmPartnerBranchFieldsToSet->iban = $headquarterFieldsToUpdate->GWIBAN;
            $vmPartnerBranchFieldsToSet->bic = strtoupper($headquarterFieldsToUpdate->GWBIC);
            $vmPartnerBranchFieldsToSet->companyNameOnInvoice = $headquarterFieldsToUpdate->NCREFIRMA;
            $vmPartnerBranchFieldsToSet->companyContactPersonOnInvoice = $headquarterFieldsToUpdate->TMFIRMENINHABER;
            $vmPartnerBranchFieldsToSet->invoiceStreet = $headquarterFieldsToUpdate->NCRESTREET;
            $vmPartnerBranchFieldsToSet->invoiceZIP = $headquarterFieldsToUpdate->NCREZIP;
            $vmPartnerBranchFieldsToSet->invoiceCity = $headquarterFieldsToUpdate->NCREORT;
            $vmPartnerBranchFieldsToSet->invoiceMail = $headquarterFieldsToUpdate->TMMAILRECHNUNG;
            $vmPartnerBranchFieldsToSet->payment = $vmPayment;


            if($i < $amountOfOriginBranches) {
                $origin_branch_company_data = $gwOriginLinkedBranches[$i];

                if(!property_exists($origin_branch_company_data, 'GGUID')) {
                    Log::error("Fehler beim Abrufen von Filiale " . $gwOriginLinkedBranches[$i]->GGUID);
                    return returnNewErrorObject('Die Daten einer oder mehrere Filialen konnten nicht abgerufen werden. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
                }

                if(!property_exists($origin_branch_company_data, 'NCFIRMENID')) {
                    Log::error("Fehler beim Abrufen von Filiale " . $gwOriginLinkedBranches[$i]->GGUID . ', Filiale enthält keine NCFIRMENID');
                    return returnNewErrorObject('Ihre Filiale enthält keine FirmenID. Bitte wenden Sie sich an den Support.', 'company_id_not_found', 500);
                }

                $originBranchUsers = getGwBranchUsers($origin_branch_company_data->GGUID);

                if(!is_array($originBranchUsers) && property_exists($originBranchUsers, 'errorMessage') && !empty($originBranchUsers->errorMessage)) {
                    return response()->json( $originBranchUsers, 500 );
                }


                $iUser = 0;
                foreach ($requestBranch->branchUsers as $requestBranchUser) {

                    if($requestBranchUser->contactPersonEmail != $originBranchUsers[$iUser]->TMADMINUSER) {
                        $emailAlreadyExists = checkIfEMailExists($requestBranchUser->contactPersonEmail);
                        if(isError($emailAlreadyExists)) {
                            return returnErrorObject($emailAlreadyExists);
                        }
                        if($emailAlreadyExists == true) {
                            return returnNewErrorObject('Es wurde bereits ein Account mit dieser E-Mail-Adresse registriert. Bitte benutzen Sie eine andere E-Mail-Adresse.', 'email_already_exists', 400);
                        }
                    }

                    $userFieldsToUpdate = new stdClass();
                    $userFieldsToUpdate->GWGENDER = $requestBranchUser->contactPersonGender;
                    $userFieldsToUpdate->TMADMINUSER = $requestBranchUser->contactPersonEmail;
                    $userFieldsToUpdate->CHRISTIANNAME = $requestBranchUser->contactPersonFirstName;
                    $userFieldsToUpdate->NAME = $requestBranchUser->contactPersonLastName;

                    if(!updateGwAddressData($originBranchUsers[$iUser]->GGUID, $userFieldsToUpdate)) {
                        Log::error('Error beim Updates des Filiale Nutzers: GGUID: ' . $originBranchUsers[$iUser]->GGUID . ', $userFieldsToUpdate: ' . json_encode($userFieldsToUpdate));
                        return returnNewErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
                    }

                    $iUser++;
                }


                if(!updateGwAddressData($origin_branch_company_data->GGUID, $tempBranch)) {
                    Log::Error('Bei /partner-personal-data ist ein Fehler aufgetreten. Die Filiale (GGUID: ' . $origin_branch_company_data->GGUID . ', Firmenname: ' . $origin_branch_company_data->COMPNAME .') konnte nicht geupdatet werden.');
                    return returnNewErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
                }


                $vmPartnerBranchFieldsToSet->companyID = $origin_branch_company_data->NCFIRMENID;
                $updatePartnerInValueMaster = addOrModifyPartnerInValueMaster($vmPartnerBranchFieldsToSet);

                if($updatePartnerInValueMaster->failed() || $updatePartnerInValueMaster == NULL) {
                    Log::Error('Registrierung der neuen Filiale ist im ValueMaster fehlgeschlagen: ' . $updatePartnerInValueMaster->body());
                    return returnNewErrorObject('Es ist ein Fehler aufgetreten. Eine Ihrer Filialen konnte nicht aktualisiert werden. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
                }

                if($updatePartnerInValueMaster && $updatePartnerInValueMaster != NULL) {
                    $partnerDataFromValueMaster = json_decode($updatePartnerInValueMaster)->d;

                    if($partnerDataFromValueMaster && $partnerDataFromValueMaster != NULL) {
                        if(!property_exists($partnerDataFromValueMaster, 'status') || strtolower($partnerDataFromValueMaster->status) != 'ok' || !empty($partnerDataFromValueMaster->error)) {
                            Log::Error('Updaten der Filiale ist im ValueMaster fehlgeschlagen: ' . $updatePartnerInValueMaster->body());
                            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Eine Ihrer Filialen konnte nicht aktualisiert werden. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
                        }
                        if(!property_exists($partnerDataFromValueMaster, 'CompanyID')) {
                            Log::Error('Updaten der Filiale ist im ValueMaster fehlgeschlagen: ' . $updatePartnerInValueMaster->body());
                            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Eine Ihrer Filialen konnte nicht aktualisiert werden. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
                        }
                    } else {
                        Log::Error('Updaten der Filiale ist im ValueMaster fehlgeschlagen: ' . $updatePartnerInValueMaster->body());
                        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Eine Ihrer Filialen konnte nicht aktualisiert werden. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
                    }
                } else {
                    Log::Error('Updaten der Filiale ist im ValueMaster fehlgeschlagen: ' . $updatePartnerInValueMaster->body());
                    return returnNewErrorObject('Es ist ein Fehler aufgetreten. Eine Ihrer Filialen konnte nicht aktualisiert werden. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
                }
            }

            if($i >= $amountOfOriginBranches) {
                //new branches added
                $dateNow = new DateTime('now');
                $dateNow->setTimezone(new DateTimeZone('Europe/Berlin'));
                $registeredSince = $dateNow->format('Y-m-d\TH:i:s');

                foreach ($requestBranch->branchUsers as $requestBranchUser) {
                    $emailAlreadyExists = checkIfEMailExists($requestBranchUser->contactPersonEmail);
                    if(isError($emailAlreadyExists)) {
                        return returnErrorObject($emailAlreadyExists);
                    }
                    if($emailAlreadyExists == true) {
                        return returnNewErrorObject('Es wurde bereits ein Account mit dieser E-Mail-Adresse registriert. Bitte benutzen Sie eine andere E-Mail-Adresse.', 'email_already_exists', 400);
                    }
                }

                $vmPartnerBranchFieldsToSet->companyID = 0;
                $vmPartnerBranchFieldsToSet->internalID = '';
                $vmPartnerBranchFieldsToSet->adminUserSex = $requestBranchUser->contactPersonGender;
                $vmPartnerBranchFieldsToSet->adminUserPreName = $requestBranchUser->contactPersonFirstName;
                $vmPartnerBranchFieldsToSet->adminUserName = $requestBranchUser->contactPersonLastName;
                $vmPartnerBranchFieldsToSet->adminUserLoginEmail = $requestBranch->branchUsers[0]->contactPersonEmail;
                $vmPartnerBranchFieldsToSet->adminUserPassword = $requestBranchUser->contactPersonPassword;
                $registerPartnerInValueMaster = addOrModifyPartnerInValueMaster($vmPartnerBranchFieldsToSet);

                if($registerPartnerInValueMaster->failed() || $registerPartnerInValueMaster == NULL) {
                    Log::Error('Registrierung der neuen Filiale ist im ValueMaster fehlgeschlagen: ' . $registerPartnerInValueMaster->body());
                    return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Partner-Account konnte nicht erstellt werden. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
                }

                if($registerPartnerInValueMaster && $registerPartnerInValueMaster != NULL) {
                    $partnerDataFromValueMaster = json_decode($registerPartnerInValueMaster)->d;

                    if($partnerDataFromValueMaster && $partnerDataFromValueMaster != NULL) {
                        if(!property_exists($partnerDataFromValueMaster, 'status') || strtolower($partnerDataFromValueMaster->status) != 'ok' || !empty($partnerDataFromValueMaster->error)) {
                            Log::Error('Registrierung der neuen Filiale ist im ValueMaster fehlgeschlagen: ' . $registerPartnerInValueMaster->body());
                            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Partner-Account konnte nicht erstellt werden. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
                        }
                        if(!property_exists($partnerDataFromValueMaster, 'CompanyID') || $partnerDataFromValueMaster->CompanyID == 0) {
                            Log::Error('Registrierung der neuen Filiale ist im ValueMaster fehlgeschlagen: ' . $registerPartnerInValueMaster->body());
                            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Partner-Account konnte nicht erstellt werden. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
                        }
                    } else {
                        Log::Error('Registrierung der neuen Filiale ist im ValueMaster fehlgeschlagen: ' . $registerPartnerInValueMaster->body());
                        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Partner-Account konnte nicht erstellt werden. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
                    }
                } else {
                    Log::Error('Registrierung der neuen Filiale ist im ValueMaster fehlgeschlagen: ' . $registerPartnerInValueMaster->body());
                    return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Partner-Account konnte nicht erstellt werden. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
                }

                $gwNewBranchCompanyResponse = Http::withHeaders([
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Accept' => 'application/json',
                    'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
                    'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
                ])->post(env('GW_API_BASE') . '/type/address', [
                    'fields' => [
                        'GWSTYPE' => 'Partnerschaft',
                        'NCFIRMENID' => strval($partnerDataFromValueMaster->CompanyID),
                        'TMARTDERPARTNERSCHAFT' => 'Partner',
                        'TMMODULEPARTNER' => 'GutscheinCARD',
                        'COMPNAME' => $tempBranch->COMPNAME,
                        'COMPNAME2' => $tempBranch->COMPNAME,
                        'STREET2' => $tempBranch->STREET2,
                        'STREET1' => $tempBranch->STREET2,
                        'TOWN2' => $tempBranch->TOWN2,
                        'TOWN1' => $tempBranch->TOWN2,
                        'ZIP2' => $tempBranch->ZIP2,
                        'ZIP1' => $tempBranch->ZIP2,
                        'CATEGORY' => $tempBranch->CATEGORY,
                        'COUNTRY2' => $tempBranch->COUNTRY2,
                        'COUNTRY1' => $tempBranch->COUNTRY2,
                        'WWWFIELDSTR1' => $tempBranch->WWWFIELDSTR1,
                        'TMMAILVEROEFFENTLICHUNG' => $tempBranch->TMMAILVEROEFFENTLICHUNG,
                        'TMPHONEVEROEFFENTLICHUNG' => $tempBranch->TMPHONEVEROEFFENTLICHUNG,
                        'NCREGION' => $headquarter_data->NCREGION,
                        'NCORTDERANMELDUNG' => $headquarter_data->NCORTDERANMELDUNG,
                        'NCREGISTRIERTSEIT' => $registeredSince,
                        'NCINTERNEID' => $headquarter_data->NCINTERNEID,
                        'TMVERTRAGID' => $headquarter_data->TMVERTRAGID,
                        'GWISCONTACT' => false,
                        'GWISCOMPANY' => true,
                        'ISORGANISATION' => true,
                        'TMPARTNERAKTIVIERT' => true,
                        'TYPSTANDORT' => 'Filiale',
                        'TMPARTNERHATGESCHLOSSENMO' => $tempBranch->TMPARTNERHATGESCHLOSSENMO,
                        'TMPARTNERHATGESCHLOSSENDI' => $tempBranch->TMPARTNERHATGESCHLOSSENDI,
                        'TMPARTNERHATGESCHLOSSENMI' => $tempBranch->TMPARTNERHATGESCHLOSSENMI,
                        'TMPARTNERHATGESCHLOSSENDO' => $tempBranch->TMPARTNERHATGESCHLOSSENDO,
                        'TMPARTNERHATGESCHLOSSENFR' => $tempBranch->TMPARTNERHATGESCHLOSSENFR,
                        'TMPARTNERHATGESCHLOSSENSA' => $tempBranch->TMPARTNERHATGESCHLOSSENSA,
                        'TMPARTNERHATGESCHLOSSENSO' => $tempBranch->TMPARTNERHATGESCHLOSSENSO,
                        'TMOEFFZEITMONTAG1VON' => $tempBranch->TMOEFFZEITMONTAG1VON,
                        'TMOEFFZEITMONTAG2VON' => $tempBranch->TMOEFFZEITMONTAG2VON,
                        'TMOEFFZEITMONTAG1BIS' => $tempBranch->TMOEFFZEITMONTAG1BIS,
                        'TMOEFFZEITMONTAG2BIS' => $tempBranch->TMOEFFZEITMONTAG2BIS,
                        'TMOEFFZEITDIENSTAG1VON' => $tempBranch->TMOEFFZEITDIENSTAG1VON,
                        'TMOEFFZEITDIENSTAG2VON' => $tempBranch->TMOEFFZEITDIENSTAG2VON,
                        'TMOEFFZEITDIENSTAG1BIS' => $tempBranch->TMOEFFZEITDIENSTAG1BIS,
                        'TMOEFFZEITDIENSTAG2BIS' => $tempBranch->TMOEFFZEITDIENSTAG2BIS,
                        'TMOEFFZEITMITTWOCH1VON' => $tempBranch->TMOEFFZEITMITTWOCH1VON,
                        'TMOEFFZEITMITTWOCH2VON' => $tempBranch->TMOEFFZEITMITTWOCH2VON,
                        'TMOEFFZEITMITTWOCH1BIS' => $tempBranch->TMOEFFZEITMITTWOCH1BIS,
                        'TMOEFFZEITMITTWOCH2BIS' => $tempBranch->TMOEFFZEITMITTWOCH2BIS,
                        'TMOEFFZEITDONNERSTAG1VON' => $tempBranch->TMOEFFZEITDONNERSTAG1VON,
                        'TMOEFFZEITDONNERSTAG2VON' => $tempBranch->TMOEFFZEITDONNERSTAG2VON,
                        'TMOEFFZEITDONNERSTAG1BIS' => $tempBranch->TMOEFFZEITDONNERSTAG1BIS,
                        'TMOEFFZEITDONNERSTAG2BIS' => $tempBranch->TMOEFFZEITDONNERSTAG2BIS,
                        'TMOEFFZEITFREITAG1VON' => $tempBranch->TMOEFFZEITFREITAG1VON,
                        'TMOEFFZEITFREITAG2VON' => $tempBranch->TMOEFFZEITFREITAG2VON,
                        'TMOEFFZEITFREITAG1BIS' => $tempBranch->TMOEFFZEITFREITAG1BIS,
                        'TMOEFFZEITFREITAG2BIS' => $tempBranch->TMOEFFZEITFREITAG2BIS,
                        'TMOEFFZEITSAMSTAG1VON' => $tempBranch->TMOEFFZEITSAMSTAG1VON,
                        'TMOEFFZEITSAMSTAG2VON' => $tempBranch->TMOEFFZEITSAMSTAG2VON,
                        'TMOEFFZEITSAMSTAG1BIS' => $tempBranch->TMOEFFZEITSAMSTAG1BIS,
                        'TMOEFFZEITSAMSTAG2BIS' => $tempBranch->TMOEFFZEITSAMSTAG2BIS,
                        'TMOEFFZEITSONNTAG1VON' => $tempBranch->TMOEFFZEITSONNTAG1VON,
                        'TMOEFFZEITSONNTAG2VON' => $tempBranch->TMOEFFZEITSONNTAG2VON,
                        'TMOEFFZEITSONNTAG1BIS' => $tempBranch->TMOEFFZEITSONNTAG1BIS,
                        'TMOEFFZEITSONNTAG2BIS' => $tempBranch->TMOEFFZEITSONNTAG2BIS,
                    ]
                ]);

                if($gwNewBranchCompanyResponse->failed()) {
                    Log::error("Fehler beim Erstellen einer neuen Filiale bei /partner-personal-data gwNewBranchCompanyResponse: " . $gwNewBranchCompanyResponse->body());
                    return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
                }

                if($gwNewBranchCompanyResponse->header('Location') == NULL || $gwNewBranchCompanyResponse->header('Location') == '') {
                    Log::error("Fehler beim Erstellen einer neuen Filiale bei /partner-personal-data bei gW, Location Header für GGUID nicht vorhanden: " . $gwNewBranchCompanyResponse->body());
                    return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
                } else {
                    $location_splitted = explode("/", $gwNewBranchCompanyResponse->header('Location'));
                    $tempBranch->GGUID = end($location_splitted);
                }


                foreach ($requestBranch->branchUsers as $requestBranchUser) {
                    //add branch user
                    $requestBranchUser->cardName = $headquarter_data->NCORTDERANMELDUNG;
                    $requestBranchUser->companyName = $headquarterFieldsToUpdate->COMPNAME;
                    if(!property_exists($requestBranchUser, 'contactPersonPartnerPortalRole') || !$requestBranchUser->contactPersonPartnerPortalRole || empty($requestBranchUser->contactPersonPartnerPortalRole)) {
                        $requestBranchUser->contactPersonPartnerPortalRole = 'User';
                    }

                    $gwNewBranchUserResponse = Http::withHeaders([
                        'Content-Type' => 'application/json; charset=utf-8',
                        'Accept' => 'application/json',
                        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
                        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
                    ])->post(env('GW_API_BASE') . '/type/address', [
                        'fields' => [
                            'TMADMINUSER' => $requestBranchUser->contactPersonEmail,
                            'GWGENDER' => $requestBranchUser->contactPersonGender,
                            'CHRISTIANNAME' => $requestBranchUser->contactPersonFirstName,
                            'NAME' => $requestBranchUser->contactPersonLastName,
                            'NCREGISTRIERTSEIT' => $registeredSince,
                            'GWISCONTACT' => true,
                            'GWISCOMPANY' => false,
                            'ISORGANISATION' => false,
                            'GWKEEPCONTACTSYNCHRON' => true,
                            'CBPHONE1' => 4,
                            'CBPHONE2' => 2,
                            'CBPHONE3' => 10,
                            'CBFAX1' => 5,
                            'CBADDRESS' => 0,
                            'PRIMARYORGANISATION' => $tempBranch->GGUID,
                            'NCAKTIV' => true,
                            'TMPARTNERPORTALROLLE' => $requestBranchUser->contactPersonPartnerPortalRole
                        ]
                    ]);


                    if($gwNewBranchUserResponse->failed()) {
                        Log::error("Fehler beim Erstellen einer neuen Filiale bei /partner-personal-data gwNewBranchUserResponse (User erstellen): " . $gwNewBranchUserResponse->body());
                        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
                    }

                    if($gwNewBranchUserResponse->successful()) {
                        Mail::to($requestBranchUser->contactPersonEmail)->send(new RegistrationNewBranchUserCustomerMail($requestBranchUser));
                    }
                }

                $addGwLink = Http::withHeaders([
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Accept' => 'application/json',
                    'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
                    'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
                ])->post(env('GW_API_BASE') . '/type/address/' . $tempBranch->GGUID . '/dossier?gguid2=' . $headquarter_data->GGUID . '&attribute=FILIALEZENTRALE&object-type2=ADDRESS');

                if($addGwLink->failed()) {
                    Log::error("Fehler beim Erstellen einer neuen Filiale bei /partner-personal-data addGwLink (Verknüpfung erstellen): " . $addGwLink->body());
                    return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
                }
            }

            $i++;
        }
    }

    if(!property_exists($headquarter_data, 'TMPARTNERDATENVOLLSTAENDIG') || $headquarter_data->TMPARTNERDATENVOLLSTAENDIG === false || $headquarter_data->TMPARTNERDATENVOLLSTAENDIG === '') {
        if(!updateGwAddressData($request->input('company_gguid'), ['TMPARTNERDATENVOLLSTAENDIG' => true])) {
            Log::Error('Bei /partner-personal-data ist ein Fehler aufgetreten. Die Zentrale konnte nicht geupdatet werden, das TMPARTNERDATENVOLLSTAENDIG konnte zum Schluss nicht gesetzt werden.');
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
        }

        $registerData = new stdClass();
        $registerData->companyName = $headquarterFieldsToUpdate->COMPNAME;
        $registerData->companyEmail = $headquarterFieldsToUpdate->MAILFIELDSTR4;
        $registerData->cardName = $personal_data->NCORTDERANMELDUNG;
        Mail::to('partnerverwaltung@trolleymaker.com')->send(new PersonalDataCompletePartnerMail($registerData));
    }

    return response()->json( new stdClass(), 200 );

})->middleware(['AuthenticateWithSession']);


function addOrModifyPartnerInValueMaster($fieldsToSet) {

    $companyName = property_exists($fieldsToSet, 'companyName') && $fieldsToSet->companyName != null ? $fieldsToSet->companyName : '';
    $companyID = property_exists($fieldsToSet, 'companyID') && $fieldsToSet->companyID != null ? $fieldsToSet->companyID : 0;
    $active = property_exists($fieldsToSet, 'active') && $fieldsToSet->active != null ? $fieldsToSet->active : 1;
    $internalID = property_exists($fieldsToSet, 'internalID') && $fieldsToSet->internalID != null ? $fieldsToSet->internalID : '';
    $phoneNumber = property_exists($fieldsToSet, 'phoneNumber') && $fieldsToSet->phoneNumber != null ? $fieldsToSet->phoneNumber : '';
    $street = property_exists($fieldsToSet, 'street') && $fieldsToSet->street != null ? $fieldsToSet->street : '';
    $zip = property_exists($fieldsToSet, 'zip') && $fieldsToSet->zip != null ? $fieldsToSet->zip : '';
    $city = property_exists($fieldsToSet, 'city') && $fieldsToSet->city != null ? $fieldsToSet->city : '';
    $country = property_exists($fieldsToSet, 'country') && $fieldsToSet->country != null ? $fieldsToSet->country : '';
    $companyEmail = property_exists($fieldsToSet, 'companyEmail') && $fieldsToSet->companyEmail != null ? $fieldsToSet->companyEmail : '';
    $bankName = property_exists($fieldsToSet, 'bankName') && $fieldsToSet->bankName != null ? $fieldsToSet->bankName : '';
    $iban = property_exists($fieldsToSet, 'iban') && $fieldsToSet->iban != null ? $fieldsToSet->iban : '';
    $bic = property_exists($fieldsToSet, 'bic') && $fieldsToSet->bic != null ? $fieldsToSet->bic : '';
    $companyNameOnInvoice = property_exists($fieldsToSet, 'companyNameOnInvoice') && $fieldsToSet->companyNameOnInvoice != null ? $fieldsToSet->companyNameOnInvoice : $companyName;
    $companyContactPersonOnInvoice = property_exists($fieldsToSet, 'companyContactPersonOnInvoice') && $fieldsToSet->companyContactPersonOnInvoice != null ? $fieldsToSet->companyContactPersonOnInvoice : '';
    $invoiceStreet = property_exists($fieldsToSet, 'invoiceStreet') && $fieldsToSet->invoiceStreet != null ? $fieldsToSet->invoiceStreet : $street;
    $invoiceZip = property_exists($fieldsToSet, 'invoiceZip') && $fieldsToSet->invoiceZip != null ? $fieldsToSet->invoiceZip : $zip;
    $invoiceCity = property_exists($fieldsToSet, 'invoiceCity') && $fieldsToSet->invoiceCity != null ? $fieldsToSet->invoiceCity : $city;
    $invoiceEmail = property_exists($fieldsToSet, 'invoiceEmail') && $fieldsToSet->invoiceEmail != null ? $fieldsToSet->invoiceEmail : $companyEmail;
    $payment = property_exists($fieldsToSet, 'payment') && $fieldsToSet->payment != null ? $fieldsToSet->payment : 'SEPA_DirectDebit';
    $adminUserSex = property_exists($fieldsToSet, 'adminUserSex') && $fieldsToSet->adminUserSex != null ? $fieldsToSet->adminUserSex : '';
    $adminUserPreName = property_exists($fieldsToSet, 'adminUserPreName') && $fieldsToSet->adminUserPreName != null ? $fieldsToSet->adminUserPreName : '';
    $adminUserName = property_exists($fieldsToSet, 'adminUserName') && $fieldsToSet->adminUserName != null ? $fieldsToSet->adminUserName : '';
    $adminUserLoginEmail = property_exists($fieldsToSet, 'adminUserLoginEmail') && $fieldsToSet->adminUserLoginEmail != null ? $fieldsToSet->adminUserLoginEmail : '';
    $adminUserPassword = property_exists($fieldsToSet, 'adminUserPassword') && $fieldsToSet->adminUserPassword != null ? $fieldsToSet->adminUserPassword : '';

    $updatePartnerInValueMaster = Http::withHeaders([
        'provider' => 'trolleymaker',
        'password' => 'poiJJ#9q9'
    ])->post('https://valuemaster.brain-behind.com/CU_WebAPI.asmx/CU_Add_Modify_Partner', [
        'CompanyName' => $companyName,
        'CompanyID' => $companyID,
        'active' => intval($active),
        'InternalID' => $internalID,
        'BusinessSector' => [],
        'PhoneNumer' => $phoneNumber,
        'Street' => $street,
        'ZIP' => $zip,
        'City' => $city,
        'Country' => $country,
        'Language' => 'de',
        'ReceiveStats' => true,
        'ShowPartner' => true,
        'ReceiveInvoice' => true,
        'ChargeTX' => true,
        'CompanyEmail' => $companyEmail,
        'Web' => '',
        'BankName' => $bankName,
        'IBAN' => $iban,
        'BIC' => $bic,
        'latitude' => 0,
        'longitute' => 0,
        'CompanyNameOnInvoice' => $companyNameOnInvoice,
        'CompanyContactPersonOnInvoice' => $companyContactPersonOnInvoice,
        'InvoiceStreet' => $invoiceStreet,
        'InvoiceZIP' => $invoiceZip,
        'InvoiceCity' => $invoiceCity,
        'InvoiceMail' => $invoiceEmail,
        'VATID' => '',
        'logo' => null,
        'Category' => [],
        'RuleSET' => '',
        'Payment' => $payment,
        'Admin_User' => [
            'Sex' => $adminUserSex,
            'PreName' => $adminUserPreName,
            'Name' => $adminUserName,
            'LoginEmail' => $adminUserLoginEmail,
            'Password' => $adminUserPassword,
            'SendWelcomeMail' => false
        ]
    ]);

    return $updatePartnerInValueMaster;
}

function updateGwDocumentData($gguid, $fieldsToUpdate) {
    return updateGwData('DOCUMENT', $gguid, $fieldsToUpdate);
}

function updateGwCardData($gguid, $fieldsToUpdate) {
    return updateGwData('KARTENVERWALTUNG', $gguid, $fieldsToUpdate);
}

function updateGwAddressData($gguid, $fields) {
    return updateGwData('ADDRESS', $gguid, $fields);
}

function updateGwPasswordData($gguid, $fields) {
    return updateGwData('PASSWORDS', $gguid, $fields);
}

function updateGwPushNotificationData($gguid, $fields) {
    return updateGwData('PUSH_NACHRICHTEN', $gguid, $fields);
}

function updateGwPerkData($gguid, $fields) {
    return updateGwData('BSPRODUCT', $gguid, $fields);
}

function updateGwOpportunityData($gguid, $fields) {
    return updateGwData('GWOPPORTUNITY', $gguid, $fields);
}

function updateGwSuaitemsData($gguid, $fields) {
    return updateGwData('SUAITEMS', $gguid, $fields);
}

function updateGwData($gwObjectType, $gguid, $fields) {

    $gwResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->get(env('GW_API_BASE') . '/type/' . $gwObjectType . '/' . $gguid);

        if ($gwResponse->failed() || $gwResponse->header('ETag') == NULL || $gwResponse->header('ETag') == '') {
                Log::error(
                    '1 updateGwData: failed for getting object (' . $gwObjectType .
                    ') by gguid (' . $gguid .
                    ') | status: ' . $gwResponse->status() .
                    ' | failed(): ' . ($gwResponse->failed() ? 'true' : 'false') .
                    ' | etag: ' . ($gwResponse->header('ETag') ?? 'NULL') .
                    ' | headers: ' . json_encode($gwResponse->headers()) .
                    ' | body: ' . substr(print_r($gwResponse->body(), TRUE), 0, 2000)
                );

                return FALSE;
    }

    $gwUpdateResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA==',
        'If-Match' => $gwResponse->header('ETag')
    ])->put(env('GW_API_BASE') . '/type/' . $gwObjectType . '/' . $gguid, [
        'fields' => $fields
    ]);

    if($gwUpdateResponse->successful()) {
        return true;
    } else {
        Log::error("2 updateGwData: failed updating object (' . $gwObjectType . '). GGUID: " . $gguid . ", Fields: " . print_r($fields, true) . "\n Response: " . print_r($gwUpdateResponse->body(), true));
        return false;
    }
}

function _deleteGwFirebaseClient($gguid) {
    return deleteGwData('FIREBASENUMMERN', $gguid);
}

function deleteGwData($gwObjectType, $gguid) {
    $gwDeleteResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->delete(env('GW_API_BASE') . '/type/' . $gwObjectType . '/' . $gguid);

    if($gwDeleteResponse->failed()) {
        Log::error("Fehler beim Löschen deleteGwData: " . $gwDeleteResponse->body());
        return createErrorObject('Es ist ein Fehler aufgetreten.', 'unkown_error', 500);
    }

    return NULL;
}

function updateGwLogo($gguid, $logoPath) {

    $gwResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->get(env('GW_API_BASE') . '/type/address/' . $gguid);

    if($gwResponse->failed() || $gwResponse->header('ETag') == NULL || $gwResponse->header('ETag') == '') {
        Log::error('updateGwLogo: failed for getting object by gguid (' . $gguid . '): ' . $gwResponse);
        return false;
    }


    $gwUpdateResponse = Http::withBody(file_get_contents($logoPath), 'image/png')->withHeaders([
        'Accept' => 'application/json',
        'Accept-Encoding' => 'gzip, deflate',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA==',
        'If-Match' => $gwResponse->header('ETag')
    ])->put(env('GW_API_BASE') . '/type/address/' . $gguid . '/image');

    if($gwUpdateResponse->successful()) {
        return true;
    } else {
        Log::error("updateGwLogo: failed updating logo: " . $gwUpdateResponse->body());
        return false;
    }
}


function getCardTransactionsFromGWForCard($cardID, $amount_of_transactions = NULL) {
    return getCardTransactionsFromGWForMultipleCards([$cardID], $amount_of_transactions);
}

function getCardTransactionsFromGWForMultipleCards($cardIDs, $amount_of_transactions = NULL, $is_amount_of_transactions_per_card = false) {

    $validator = Validator::make([
        'cardIDs'                => $cardIDs,
        'amount_of_transactions' => $amount_of_transactions
    ], [
        'cardIDs'                => 'required|array',
        'cardIDs.*'              => 'integer',
        'amount_of_transactions' => 'nullable|numeric'
    ]);

    if ($validator->fails()) {
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'no_cardID', 400);
    }

    $response_array = [];

   $url = env('GW_API_BASE') . '/query';
    if($amount_of_transactions != NULL && $amount_of_transactions > 0 && $is_amount_of_transactions_per_card == false) {
        $url .= '?entries-per-page=' . $amount_of_transactions . '&page=1';
    }

    $query = 'SELECT t.TADKARTENNUMMER, t.TADBUCHUNGSDATUM, t.TADBUCHUNGSARTUEBERSETZUNG, t.TADBUCHUNGSART, t.TADBETRAG, t.TADPARTNER, b.TMNAMEDESBONUS FROM TRANSAKTIONSDATEN as t LEFT JOIN BONI as b ON b.TMVOUCHERID = t.TMVOUCHERID AND t.TMVOUCHERID <> 0  WHERE TADKARTENNUMMER IN (';
    $i = 0;
    foreach($cardIDs as $cardID) {
        if($i > 0) {
            $query .= ', ';
        }
        $query .= '"' . $cardID . '"';
        $response_array[strval($cardID)] = [];
        $i++;
    }
    $query .= ') AND TADBUCHUNGSART != "Einkauf" ORDER BY TADBUCHUNGSDATUM DESC';

    $gwResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post($url, [
        'query' => $query
    ]);

    if($gwResponse->failed()) {
        Log::error('Failed to get transactions: ' . print_r($gwResponse->body(), true));
        $error_obj = new stdClass();
        $error_obj->errorMessage = 'Es ist ein Fehler aufgetreten.';
        return $error_obj;
    }

    $gwTransactionsData = json_decode($gwResponse);

    if(count($gwTransactionsData) > 0) {
        foreach ($gwTransactionsData[0]->rows as $row) {

            if($is_amount_of_transactions_per_card == true) {
                if(count($response_array[strval($row->TADKARTENNUMMER)]) >= $amount_of_transactions) {
                    continue;
                }
            }

            $response = new stdClass();

            if(property_exists($row, 'TADBUCHUNGSART')) {
                $lowercasedBuchungsart = strtolower($row->TADBUCHUNGSART);
                if($lowercasedBuchungsart == "einkauf" || $lowercasedBuchungsart == "disagio" || $lowercasedBuchungsart == "terminalfreischalt" || $lowercasedBuchungsart == "terminalfreischaltung") {
                    continue;
                }
            }

            if(property_exists($row, 'GWSTYPE')) {
                $lowercasedType = strtolower($row->GWSTYPE);
                if($lowercasedType != "buchung") {
                    continue;
                }
            }

            if(property_exists($row, 'TMKUNDETXIGNORIEREN') && $row->TMKUNDETXIGNORIEREN == true) {
                continue;
            }

            if(property_exists($row, 'TADBUCHUNGSDATUM')) {
                $transactionsDate = new DateTime($row->TADBUCHUNGSDATUM, new DateTimeZone('UTC'));
                $transactionsDate->setTimezone(new DateTimeZone('Europe/Berlin'));
                $date = $transactionsDate->format('d.m.Y H:i:s');
                $response->date = convertDateWithFormatToISODate($date, 'd.m.Y H:i:s');
                $response->dateFormattedDE = $transactionsDate->format('d.m.Y H:i:s');
            } else {
                $response->date = '';
                $response->dateFormattedDE = '';
            }

            if(property_exists($row, 'TADBETRAG') && $row->TADBETRAG != 0) {
                $betrag = number_format($row->TADBETRAG, 2, '.', '');
                $betrag = floatval($betrag);
                $response->amountCent = intval(strval($betrag * 100));
                $response->amountPositive = abs($response->amountCent);
                $response->amountFormattedDE = number_format($row->TADBETRAG, 2, ',', '.');
            } else {
                $response->amountCent = 0;
                $response->amountFormattedDE = '0.0';
            }

            if(property_exists($row, 'TADPARTNER')) {
                $response->partner = $row->TADPARTNER;
            } else {
                $response->partner = '';
            }

            if(property_exists($row, 'TADBUCHUNGSARTUEBERSETZUNG')) {
                $response->text = $row->TADBUCHUNGSARTUEBERSETZUNG;

                if (property_exists($row, 'TMNAMEDESBONUS')) {
                    $response->text .= " " . $row->TMNAMEDESBONUS;
                }
            } else {
                $response->text = '';
            }

            array_push($response_array[strval($row->TADKARTENNUMMER)], $response);
        }
    }

    return $response_array;
}

function getPartnerTransactionsFromGW($partnerGGUID, $amount_of_transactions = NULL, $from_date = NULL, $to_date = NULL, $censor_cardId = true) {
    return getTransactionsFromGWLinkedTo('ADRTADPARTNER', $partnerGGUID, $amount_of_transactions, $from_date, $to_date, $censor_cardId);
}

function getTransactionsFromGWLinkedTo($linked_to, $company_gguid, $amount_of_transactions = NULL, $from_date = NULL, $to_date = NULL, $censor_cardId = true) {

    $url = env('GW_API_BASE') . '/type/TRANSAKTIONSDATEN/full?linked-to=' . $company_gguid . '&linked-to-type=ADDRESS&linked-to-attributes=' . $linked_to . '&order-by=TADBUCHUNGSDATUM DESC';
    if($amount_of_transactions != NULL && $amount_of_transactions > 0) {
        $url .= '&entries-per-page=' . $amount_of_transactions . '&page=1';
    }

    $gwResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->get($url);

    if($gwResponse->failed()) {
        Log::error('Failed to get transactions for GGUID: ' . $company_gguid);
        return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
    }

    $gwTransactionsData = json_decode($gwResponse);

    $responseArray = array();
    if(count($gwTransactionsData) > 0) {
        foreach ($gwTransactionsData as $transactionObject) {

            $row = $transactionObject->fields;
            $response = new stdClass();

            if (property_exists($row, 'TADBUCHUNGSART')) {
                $lowercasedBuchungsart = strtolower($row->TADBUCHUNGSART);
                if ($lowercasedBuchungsart == "einkauf" || $lowercasedBuchungsart == "disagio" || $lowercasedBuchungsart == "terminalfreischalt" || $lowercasedBuchungsart == "terminalfreischaltung") {
                    continue;
                }
            }

             if(property_exists($row, 'GWSTYPE')) {
                $lowercasedType = strtolower($row->GWSTYPE);
                if($lowercasedType != "buchung") {
                    continue;
                }
            }

            if(property_exists($row, 'TMPARTNERTXIGNORIEREN') && $row->TMPARTNERTXIGNORIEREN == true) {
                continue;
            }

            $response->id = $row->GGUID;

            if(property_exists($row, 'TADBUCHUNGSDATUM')) {
                $dateTimeBookingDate = new DateTime($row->TADBUCHUNGSDATUM, new DateTimeZone('UTC'));
                $dateTimeBookingDate->setTimezone(new DateTimeZone('Europe/Berlin'));

                if($from_date != NULL && $dateTimeBookingDate < $from_date) {
                    continue;
                }

                if($to_date != NULL && $dateTimeBookingDate > $to_date) {
                    continue;
                }

                $response->date = $dateTimeBookingDate->getTimestamp();
                $response->dateFormatted = $dateTimeBookingDate->format('d.m.Y');
                $response->time = $dateTimeBookingDate->format('H:i:s');
            } else {
                $response->date = '';
                $response->time = '';
            }

            if(property_exists($row, 'TADBETRAG') && $row->TADBETRAG != 0 ) {
                $response->amount = $row->TADBETRAG; //number_format($row->TADBETRAG, 2, ',', '.');
                $response->amountPositive = abs($row->TADBETRAG);
            } else {
                $response->amount = '';
            }

            if(property_exists($row, 'TADKARTENNUMMER')) {
                if($censor_cardId == true) {
                    $cardID = substr($row->TADKARTENNUMMER, 0, 4);
                    $cardID .= 'xxxxxxxx';
                    $cardID .= substr($row->TADKARTENNUMMER, -3);
                    $response->cardID = $cardID;
                } else {
                    $response->cardID = strval($row->TADKARTENNUMMER);
                }

                $response->originalCardID = $row->TADKARTENNUMMER;
            } else {
                $response->cardID = '';
                $response->originalCardID = '';
            }

            if(property_exists($row, 'TADBUCHUNGSARTUEBERSETZUNG')) {
                $response->text = $row->TADBUCHUNGSARTUEBERSETZUNG;

                if (property_exists($row, 'TMNAMEDESBONUS')) {
                    $response->text .= " " . $row->TMNAMEDESBONUS;
                }
            } else {
                $response->text = '';
            }

            array_push($responseArray, $response);
        }
    } else {
        return [];
    }

    return $responseArray;
}

function getTransactionsFromGWByRegion($region_card_name, $amount_of_transactions = NULL, $date_from = NULL) {

    $validator = Validator::make([
        'region_card_name'       => $region_card_name,
        'amount_of_transactions' => $amount_of_transactions
    ], [
        'region_card_name'       => 'required|alpha_num',
        'amount_of_transactions' => 'nullable|numeric'
    ]);

    if ($validator->fails()) {
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'no_cardID', 400);
    }

    $response_array = [];

    $url = env('GW_API_BASE') . '/query';
    if($amount_of_transactions != NULL && $amount_of_transactions > 0) {
        $url .= '?entries-per-page=' . $amount_of_transactions . '&page=1';
    } else {
        $url .= '?entries-per-page=99999&page=1';
    }

    $query = 'SELECT GGUID, TADKARTENNUMMER, TADBUCHUNGSDATUM, TADBUCHUNGSARTUEBERSETZUNG, TADBUCHUNGSART, TADBETRAG, TADPARTNER, GWSTYPE, GWSSTATUS, NCREFERENZ, TMORTDERANMELDUNG, TADSTICHWORT FROM TRANSAKTIONSDATEN WHERE TMORTDERANMELDUNG="' . $region_card_name . '" AND TADBETRAG != 0';
    if(!empty($date_from)) {
        $query .= 'AND TADBUCHUNGSDATUM > "' . $date_from . '"';
    }
    $query .= 'ORDER BY TADBUCHUNGSDATUM DESC';

    $gwResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post($url, [
        'query' => $query
    ]);

    if($gwResponse->failed()) {
        Log::error('Failed to get getTransactionsFromGWByRegion: ' . print_r($gwResponse->body(), true));
        $error_obj = new stdClass();
        $error_obj->errorMessage = 'Es ist ein Fehler aufgetreten.';
        return $error_obj;
    }

    $gwTransactionsData = json_decode($gwResponse);

    if(count($gwTransactionsData) > 0) {
        $response_array = $gwTransactionsData[0]->rows;
    }

    return $response_array;
}

Route::get('/interest-registration-form-values', function (Request $request) {
    $values = _getSuggestedValuesForAddress(['GWBRANCH', 'TMADMINUSERROLLE']);

    if(isError($values)){
        return returnErrorObject($values);
    }

    $values['sectors'] = $values['GWBRANCH'];
    unset($values['GWBRANCH']);
    $values['contact_person_roles'] = $values['TMADMINUSERROLLE'];
    unset($values['TMADMINUSERROLLE']);

    if($request->has('id') && !empty($request->input('id'))) {
        $hashedGGUID = $request->input('id');
        $decryptedGGUID = decryptURLGGUID($hashedGGUID);
        if(isError($decryptedGGUID) || empty($decryptedGGUID)) {
            Log::error('Fehler beim Entschlüsseln der GGUID aus URL Parameter. decryptedGGUID: ' . print_r($decryptedGGUID, true));
            return response()->json($values, 200);
        }

        $prefillData = isPrefilledInterestPartnerOrCustomer($decryptedGGUID, $hashedGGUID);
        if(!property_exists($prefillData, 'isAllowedToPrefill') || $prefillData->isAllowedToPrefill == false || !property_exists($prefillData, 'company_data') || !is_object($prefillData->company_data)) {
            return response()->json($values, 200);
        }

        $company_data = $prefillData->company_data;
        $values['companyName'] = property_exists($company_data, 'COMPNAME') && !empty($company_data->COMPNAME) ? $company_data->COMPNAME : '';
        $values['companyAddressAdditional'] = property_exists($company_data, 'GWADDITIONALINFO1') && !empty($company_data->GWADDITIONALINFO1) ? $company_data->GWADDITIONALINFO1 : '';
        $values['companyStreet'] = property_exists($company_data, 'STREET1') && !empty($company_data->STREET1) ? $company_data->STREET1 : '';
        $values['companyZip'] = property_exists($company_data, 'ZIP1') && !empty($company_data->ZIP1) ? $company_data->ZIP1 : '';
        $values['companyCity'] = property_exists($company_data, 'TOWN1') && !empty($company_data->TOWN1) ? $company_data->TOWN1 : '';
        $values['companyCountry'] = property_exists($company_data, 'COUNTRY1') && !empty($company_data->COUNTRY1) ? $company_data->COUNTRY1 : '';
        $values['prefilledPartner'] = $hashedGGUID;
    }

    return response()->json($values, 200);
});


Route::post('/interest-registration', function (Request $request) {

    foreach(array('companyName', 'companyStreet', 'companyZip', 'companyCity', 'companyCountry', 'contactPersonGender', 'contactPersonFirstName', 'companySector',
                  'contactPersonLastName', 'contactPersonEmail', 'contactPersonEmailRepeated', 'ceoPhone', 'contactPersonPassword', 'contactPersonPasswordRepeated', 'contactPersonRole', 'partnerInterestedIn') as $input) {
        if(!$request->has($input) || $request->input($input) == NULL || $request->input($input) == '') {
            return returnNewErrorObject('Es wurden nicht alle erforderlichen Felder ausgefüllt!', 'invalid_form', 400);
        }
    }

    $registerData = new stdClass();

    if(!$request->has('cardName') || $request->input('cardName') == NULL || $request->input('cardName') == '') {
        Log::error("Fehler bei Registrierung eines Interessenten: Es wurde kein CardName mitgeschickt");
        sendErrorNotificationMail('Fehler bei Registrierung eines Interessenten: Es wurde kein cardName mitgeschickt.');
        return returnNewErrorObject('Bei der Regionen-Zuordnung ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'no_cardName', 400);
    } else {
        $registerData->cardName = $request->input('cardName');
    }

    if(!$request->has('region') || $request->input('region') == NULL || $request->input('region') == '') {
        Log::error("Fehler bei Registrierung eines Interessenten: Es wurde kein Regionname (region) mitgeschickt");
        sendErrorNotificationMail('Fehler bei Registrierung eines Interessenten: Es wurde kein Regionname (region) mitgeschickt.');
        return returnNewErrorObject('Bei der Regionen-Zuordnung ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'no_region', 400);
    } else {
        $registerData->region = $request->input('region');
    }

    if($registerData->region != 'Leinfelden-Echterdingen') {
        if(!$request->has('companyGewerbeverein') || $request->input('companyGewerbeverein') == NULL || $request->input('companyGewerbeverein') == '') {
            return returnNewErrorObject('Das Feld der Mitgliedschaft im Gewerbeverein wurde nicht ausgefüllt.', 'no_companyGewerbeverein', 400);
        }
    }

    if($request->input('contactPersonEmail') != $request->input('contactPersonEmailRepeated')) {
        return returnNewErrorObject('Die beiden E-Mail Adressen stimmen nicht überein!', 'emails_not_matching', 400);
    } else {
        if(!str_contains($request->input('contactPersonEmail'), '@')) {
            return returnNewErrorObject('Die E-Mail-Adresse ist ungültig.', 'invalid_email', 400);
        }
        $registerData->email = trim($request->input('contactPersonEmail'));
    }

    if(!is_numeric($request->input('ceoPhone'))) {
        return returnNewErrorObject('Die Telefonnummer ist ungültig! Sie darf nur Zahlen enthalten.', 'invalid_phone', 400);
    } else {
        $registerData->ceoPhone = trim($request->input('ceoPhone'));
    }


    $emailAlreadyExists = checkIfEMailExists($registerData->email);
    if(isError($emailAlreadyExists)) {
        return returnErrorObject($emailAlreadyExists);
    }
    if($emailAlreadyExists == true) {
        return returnNewErrorObject('Es wurde bereits ein Account mit dieser E-Mail-Adresse registriert. Bitte benutzen Sie eine andere E-Mail-Adresse.', 'email_already_exists', 400);
    }


    if(strlen($request->input('companyZip')) == 4 || strlen($request->input('companyZip')) == 5) {
        $registerData->zip = trim($request->input('companyZip'));
    } else {
        return returnNewErrorObject('Die Postleitzahl darf nur aus 4 oder 5 Zahlen bestehen.', 'invalid_zip', 400);
    }

    if(strlen($request->input('companyCountry')) == 2) {
        $registerData->country = trim($request->input('companyCountry'));
    } else {
        return returnNewErrorObject('Ungültiger Ländercode. Bitte wenden Sie sich an den Support.', 'invalid_country', 400);
    }

    if($request->input('partnerInterestedIn') != 'Akzeptanzpartner' && $request->input('partnerInterestedIn') != 'Arbeitgeber' && $request->input('partnerInterestedIn') != 'Beides') {
        return returnNewErrorObject('Das Feld für "Ich bin interessiert an.." enthält einen ungültigen Wert.', 'invalid_partnerInterestedIn', 400);
    }

    if(!$request->has('community') || $request->input('community') == NULL || $request->input('community') == '') {
        $registerData->community = $registerData->region;
    } else {
        $registerData->community = $request->input('community');
    }

    $registerData->gender = trim($request->input('contactPersonGender'));
    $registerData->firstName = trim($request->input('contactPersonFirstName'));
    $registerData->lastName = trim($request->input('contactPersonLastName'));
    $registerData->companyName = trim($request->input('companyName'));
    $registerData->street = trim($request->input('companyStreet'));
    $registerData->city = trim($request->input('companyCity'));
    $registerData->contactPersonRole = trim($request->input('contactPersonRole'));
    $registerData->companyGewerbeverein = trim($request->input('companyGewerbeverein'));
    $registerData->partnerInterestedIn = trim($request->input('partnerInterestedIn'));
    $registerData->companySector = trim($request->input('companySector'));

    //TODO: title in Formular einbauen und dann hier als Parameter mitgeben
    $guessedSalutation = _guessSalutationFromGW($registerData->firstName, $registerData->lastName, $registerData->gender, '', $registerData->country);
    $registerData->addressterm = $guessedSalutation->addressterm;
    $registerData->addressletter = $guessedSalutation->addressletter;


    $dateNow = new DateTime('now');
    $dateNow->setTimezone(new DateTimeZone('Europe/Berlin'));
    $registerData->registeredSince = $dateNow->format('Y-m-d\TH:i:s');

    if(strlen($request->input('contactPersonPassword')) > 50) {
        return returnNewErrorObject('Das Passwort darf maximal 50 Zeichen lang sein!', 'invalid_password', 400);
    }

    if($request->input('contactPersonPassword') != $request->input('contactPersonPasswordRepeated')) {
        return returnNewErrorObject('Die beiden Passwörter stimmen nicht überein!', 'passwords_not_matching', 400);
    } else {
        $registerData->password = $request->input('contactPersonPassword');
    }

    $getRegionData = Http::withoutVerifying()->withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
    ])->get(config('services.wordpress.regions.endpoint') . '_fields=acf&region_name=' . $registerData->region);

    if($getRegionData->failed()) {
        sendErrorNotificationMail('Bei Interessenten-Registrierung konnte die Regionen vom Master-Wordpress nicht abgerufen werden. Region: ' . $registerData->region);
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Die Regionsdaten konnten nicht abgerufen werden. Bitte wenden Sie sich an den Support.', 'no_regions', 500);
    }

    $regionData = json_decode($getRegionData);
    if($regionData && count($regionData) > 1) {
        sendErrorNotificationMail('Bei Interessenten-Registrierung konnte die Regionen nicht eindeutig zugeordnet werden. Region: ' . $registerData->region);
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Die Region konnte nicht eindeutig zugeordnet werden. Bitte wenden Sie sich an den Support.', 'multiple_regions', 400);
    } else {
        $regionData = $regionData[0]->acf;
    }

    $registerData->contractNumber = generateContractNumber($regionData->contract_number_prefix);

    if($request->has('companyAddressAdditional')) {
        $registerData->companyAddressAdditional = $request->input('companyAddressAdditional');
    } else {
        $registerData->companyAddressAdditional = '';
    }

    $registerData->sktype = '';
    $sktype = array();
    if(property_exists($regionData, 'new_interests_no_einrichtungsgebuehr_gutscheincard') && is_bool($regionData->new_interests_no_einrichtungsgebuehr_gutscheincard) && $regionData->new_interests_no_einrichtungsgebuehr_gutscheincard === true) {
        array_push($sktype, 'Keine Einrichtungsgebühr GutscheinCARD');
    }
    if(property_exists($regionData, 'new_interests_no_einrichtungsgebuehr_mitarbeitercard') && is_bool($regionData->new_interests_no_einrichtungsgebuehr_mitarbeitercard) && $regionData->new_interests_no_einrichtungsgebuehr_mitarbeitercard === true) {
        array_push($sktype, 'Keine Einrichtungsgebühr MitarbeiterCARD');
    }

    if(count($sktype) > 0) {
        $registerData->sktype = implode(',', $sktype);
    }


    $companyFields = new stdClass();
    $companyFields->GWSTYPE = 'Interessent';
    $companyFields->MAILFIELDSTR5 = $registerData->email;
    $companyFields->COMPNAME = $registerData->companyName;
    $companyFields->GWADDITIONALINFO1 = $registerData->companyAddressAdditional;
    $companyFields->STREET1 = $registerData->street;
    $companyFields->TOWN1 = $registerData->city;
    $companyFields->ZIP1 = $registerData->zip;
    $companyFields->COUNTRY1 = $registerData->country;
    $companyFields->PHONEFIELDSTR9 = $registerData->ceoPhone;
    $companyFields->TMGEWERBEVEREININFOMITGLIED = $registerData->companyGewerbeverein;
    $companyFields->TMPARTNERINTERESSE = $registerData->partnerInterestedIn;
    $companyFields->NCREGION = $registerData->region;
    $companyFields->NCORTDERANMELDUNG = $registerData->cardName;
    $companyFields->NCREGISTRIERTSEIT = $registerData->registeredSince;
    $companyFields->NCINTERNEID = $registerData->contractNumber;
    $companyFields->TMVERTRAGID = $registerData->contractNumber;
    $companyFields->TMGEMEINDEZUGEHOERIGKEIT = $registerData->community;
    $companyFields->GWISCONTACT = false;
    $companyFields->GWISCOMPANY = true;
    $companyFields->ISORGANISATION = true;
    $companyFields->TMPARTNERAKTIVIERT = false;
    $companyFields->TYPSTANDORT = 'Zentrale';
    $companyFields->TMARTDERSK = $registerData->sktype;
    $companyFields->GWBRANCH = $registerData->companySector;


    $contactPersonFields = new stdClass();
    $contactPersonFields->TMADMINUSER = $registerData->email;
    $contactPersonFields->MAILFIELDSTR1 = $registerData->email;
    $contactPersonFields->GWGENDER = $registerData->gender;
    $contactPersonFields->ADDRESSTERM = $registerData->addressterm;
    $contactPersonFields->ADDRESSLETTER = $registerData->addressletter;
    $contactPersonFields->CHRISTIANNAME = $registerData->firstName;
    $contactPersonFields->NAME = $registerData->lastName;
    $contactPersonFields->NCREGISTRIERTSEIT = $registerData->registeredSince;
    $contactPersonFields->GWISCONTACT = true;
    $contactPersonFields->GWISCOMPANY = false;
    $contactPersonFields->ISORGANISATION = false;
    $contactPersonFields->GWKEEPCONTACTSYNCHRON = true;
    $contactPersonFields->CBPHONE1 = 4;
    $contactPersonFields->CBPHONE2 = 2;
    $contactPersonFields->CBPHONE3 = 10;
    $contactPersonFields->CBFAX1 = 5;
    $contactPersonFields->CBADDRESS = 0;
    $contactPersonFields->TMADMINUSERROLLE = $registerData->contactPersonRole;
    $contactPersonFields->NCAKTIV = true;
    $contactPersonFields->TMPARTNERPORTALROLLE = 'Admin';

    $isPrefilledPartner = false;
    if($request->has('prefilledPartner') && !empty($request->input('prefilledPartner'))) {
        $hashedGGUID = $request->input('prefilledPartner');
        $decryptedGGUID = decryptURLGGUID($hashedGGUID);
        if(!isError($decryptedGGUID) && !empty($decryptedGGUID)) {
            $prefillData = isPrefilledInterestPartnerOrCustomer($decryptedGGUID, $hashedGGUID);
            if(property_exists($prefillData, 'isAllowedToPrefill') && $prefillData->isAllowedToPrefill == true && property_exists($prefillData, 'company_data') && is_object($prefillData->company_data)) {
                $companyFields->TMPARTNERAKTIVIERT = true;
                $isPrefilledPartner = true;
            }
        }
    }

    $foundAContactPersonToUpdate = false;
    if($isPrefilledPartner) { // = registration comes from personalized prefilled url

        if(!updateGwAddressData($decryptedGGUID, $companyFields)) {
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
        }

        $contactPersonFields->PRIMARYORGANISATION = $decryptedGGUID;

        $gwContactPersons = getGwContactPersonsForCompanyGGUID($decryptedGGUID);

        if(!isError($gwContactPersons) && is_array($gwContactPersons) && count($gwContactPersons) > 0) {
            foreach ($gwContactPersons as $contactPerson) {
                if(property_exists($contactPerson, 'MAILFIELDSTR1') && $contactPerson->MAILFIELDSTR1 == $registerData->email && property_exists($contactPerson, 'CHRISTIANNAME') && $contactPerson->CHRISTIANNAME == $registerData->firstName && property_exists($contactPerson, 'NAME') && $contactPerson->NAME == $registerData->lastName) {
                    //if filled out user is same as contact person in GW, then update contact person record
                    if(!is_object($contactPerson) || !property_exists($contactPerson, 'GGUID') || empty($contactPerson->GGUID)) {
                        Log::error('Fehler bei Interessentenregistrierung für prefillPartners: Der erste Anrepchaprtner von Datensatz ' . print_r($decryptedGGUID, true) . ', ' . print_r($gwContactPersons, true) . ' hat keine GGUID.');
                        sendErrorNotificationMail('Fehler bei Interessentenregistrierung für prefillPartners: Der erste Anrepchaprtner von Datensatz ' . print_r($decryptedGGUID, true) . ', ' . print_r($gwContactPersons, true) . ' hat keine GGUID.');
                        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
                    }
                    $foundAContactPersonToUpdate = true;
                    if(!updateGwAddressData($contactPerson->GGUID, $contactPersonFields)) {
                        Log::error('Fehler bei Interessentenregistrierung für prefillPartners: Der Ansprechpartner von Datensatz ' . print_r($decryptedGGUID, true) . ', ' . print_r($contactPerson, true) . ' konnte nicht geupdatet werden. Fields: ' . print_r($contactPersonFields, true));
                        sendErrorNotificationMail('Fehler bei Interessentenregistrierung für prefillPartners: Der Ansprechpartner von Datensatz ' . print_r($decryptedGGUID, true) . ', ' . print_r($contactPerson, true) . ' konnte nicht geupdatet werden. Fields: ' . print_r($contactPersonFields, true));
                        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
                    }

                    $registerData->personal_gguid = $contactPerson->GGUID;
                }
            }
        } else {
            Log::error('Hinweis bei Interessentenregistrierung für prefillPartners: Für Datensatz ' . print_r($decryptedGGUID, true) . ' wurde kein Ansprechpartner-Datensatz gefunden, deshalb wird ein neuer angelegt. Data: ' . print_r($gwContactPersons, true));
        }


        $checkGwOpportunityLink = Http::withHeaders([
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
            'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
        ])->get(env('GW_API_BASE') . '/type/GWOPPORTUNITY/full?linked-to=' . $decryptedGGUID .'&linked-to-type=ADDRESS&linked-to-attributes=ACCOUNT');

        if($checkGwOpportunityLink->failed()) {
            Log::error("Fehler beim Abrufen der Verknüpfungen der GWOPPORTUNITY in /interest-registration für: " . $decryptedGGUID . ", Response: " . $checkGwOpportunityLink->body());
            sendErrorNotificationMail("Fehler beim Abrufen der Verknüpfungen der GWOPPORTUNITY in /interest-registration für: " . $decryptedGGUID . ", Response: " . $checkGwOpportunityLink->body());
        }

        $opportunities = json_decode($checkGwOpportunityLink->body());


        $partnerModules = [];
        switch (strtolower($companyFields->TMPARTNERINTERESSE)) {
            case 'akzeptanzpartner':
                array_push($partnerModules, 'GutscheinCARD');
                break;
            case 'arbeitgeber':
                array_push($partnerModules, 'MitarbeiterCARD');
                break;
            case 'beides':
                array_push($partnerModules, 'MitarbeiterCARD', 'GutscheinCARD');
                break;
            default:
                break;
        }

        Log::error(print_r($opportunities, true));
        foreach ($partnerModules as $partnerModule) {
            $foundGwOpportunity = false;
            if(is_array($opportunities) && count($opportunities) > 0) {
                foreach ($opportunities as $opportunity) {
                    $opportunity = $opportunity->fields;
                    if(property_exists($opportunity, 'STATUS') && strtolower($opportunity->STATUS) != 'gewonnen' &&
                        property_exists($opportunity, 'TMKATEGORIE') && strtolower($opportunity->TMKATEGORIE) == 'partnerschaft' &&
                        property_exists($opportunity, 'TMMODULE') && strtolower($opportunity->TMMODULE) == strtolower($partnerModule)) {
                            $foundGwOpportunity = true;
                            $opportunityFieldsToUpdate = new stdClass();
                            $opportunityFieldsToUpdate->DISTRIBUTIONPHASE = 'Anmeldung Interessent';
                            if(!updateGwOpportunityData($opportunity->GGUID, $opportunityFieldsToUpdate)) {
                                Log::error("interest-registration: failed updating GWOPPORTUNITY: " . $opportunity->GGUID . " Fields: " . print_r($opportunityFieldsToUpdate, true));
                                sendErrorNotificationMail("interest-registration: failed updating GWOPPORTUNITY: " . $opportunity->GGUID . " Fields: " . print_r($opportunityFieldsToUpdate, true));
                            }
                    }
                }
            }
            if(!$foundGwOpportunity) {
                //no existing opportunity found so create one
                $opportunityFields = new stdClass();
                $opportunityFields->ACCOUNTGUID = $decryptedGGUID;
                $opportunityFields->ACCOUNTINFORMATION = $companyFields->COMPNAME;
                $opportunityFields->DISTRIBUTIONPHASE = 'Anmeldung Interessent';
                $opportunityFields->KEYWORD = 'Partnerschaft | ' . $partnerModule . ' | ' . $opportunityFields->ACCOUNTINFORMATION;
                $opportunityFields->SOURCE = 'Auftraggeber, Potentialliste';
                $opportunityFields->STATUS = 'offen';
                $opportunityFields->TMKATEGORIE = 'Partnerschaft';
                $opportunityFields->TMMODULE = $partnerModule;

                $createdOpportunityGGUID = _createOpportunityInGw($opportunityFields);
                if(isError($createdOpportunityGGUID)) {
                    Log::error("interest-registration: failed creating GWOPPORTUNITY Fields: " . print_r($opportunityFields, true));
                    sendErrorNotificationMail("interest-registration: failed updating GWOPPORTUNITY Fields: " . print_r($opportunityFields, true));
                }

                $addGwLink = Http::withHeaders([
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Accept' => 'application/json',
                    'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
                    'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
                ])->post(env('GW_API_BASE') . '/type/ADDRESS/' . $decryptedGGUID . '/dossier?gguid2=' . $createdOpportunityGGUID . '&attribute=ACCOUNT&object-type2=GWOPPORTUNITY');

                if($addGwLink->failed()) {
                    Log::error("Fehler beim Erstellen einer neuen Verknüpfung von Opportunity zu Adresse: " . $addGwLink->body());
                    sendErrorNotificationMail("Fehler beim Erstellen einer neuen Verknüpfung von Opportunity zu Adresse: " . $addGwLink->body());
                }
            }
        }

    } else {
        $gwInterestCompanyResponse = Http::withHeaders([
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
            'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
        ])->post(env('GW_API_BASE') . '/type/address', [
            'fields' => $companyFields
        ]);

        if($gwInterestCompanyResponse->failed()) {
            Log::error("Fehler bei Registrierung des Interesent FIRMA bei gwInterestCompanyResponse: " . $gwInterestCompanyResponse->body() . '\n\nfür E-Mail-Adresse: ' . $registerData->email);
            sendErrorNotificationMail('Fehler bei Registrierung des Interesent FIRMA bei gwInterestCompanyResponse: ' . $gwInterestCompanyResponse->body() . '\n\nfür E-Mail-Adresse: ' . $registerData->email);
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unkown_error', 500);
        }

        if($gwInterestCompanyResponse->header('Location') == NULL || $gwInterestCompanyResponse->header('Location') == '') {
            Log::error("Fehler bei Registrierung des Interesent FIRMA bei gW, Location Header für GGUID nicht vorhanden: " . $gwInterestCompanyResponse->body() . '\n\nfür E-Mail-Adresse: ' . $registerData->email);
            sendErrorNotificationMail('Fehler bei Registrierung des Interesent FIRMA bei gW, Location Header für GGUID nicht vorhanden: ' . $gwInterestCompanyResponse->body() . '\n\nfür E-Mail-Adresse: ' . $registerData->email);
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unkown_error', 500);
        } else {
            $location_splitted = explode("/", $gwInterestCompanyResponse->header('Location'));
            $registerData->company_gguid = end($location_splitted);
        }

        $contactPersonFields->PRIMARYORGANISATION = $registerData->company_gguid;
    }


    if(!$isPrefilledPartner || !$foundAContactPersonToUpdate) {

        $gwInterestResponse = Http::withHeaders([
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
            'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
        ])->post(env('GW_API_BASE') . '/type/address', [
            'fields' => $contactPersonFields
        ]);

        $dateNow = new DateTime('now');
        $dateNow->setTimezone(new DateTimeZone('Europe/Berlin'));
        $registerData->registeredSince = $dateNow->format('d.m.Y H:i:s');

        if($gwInterestResponse->failed() && !$isPrefilledPartner) {
            sendErrorNotificationMail('interest person creation failed for email: ' . $registerData->email);
            //interest person creation failed, so delete the created company, but only if not prefilled
            $gwDeleteFailedInterestResponse = Http::withHeaders([
                'Content-Type' => 'application/json; charset=utf-8',
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
                'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
            ])->delete(env('GW_API_BASE') . '/type/address/' . $registerData->company_gguid);

            if($gwDeleteFailedInterestResponse->successful()) {
                Log::error("Fehler bei Registrierung des Interesent bei gW: " . $gwInterestResponse->body());
                return returnNewErrorObject('Es ist ein Fehler aufgetreten.', 'unkown_error', 500);
            } else {
                Log::error("Fehler bei Registrierung des Interesent bei gW: Firma wurde erstellt, aber Ansprechpartner konnte nicht erstellt werden. Das daraufhin löschen der Firma ist fehlgeschalgen: " . print_r($gwDeleteFailedInterestResponse->body(), true));
                return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unkown_error', 500);
            }
        }

        Log::error('gwInterestResponse: ' . $gwInterestResponse->body());

        if($gwInterestResponse->header('Location') == NULL || $gwInterestResponse->header('Location') == '') {
            Log::error("Fehler bei Registrierung des Interesent FIRMA bei gW, Location Header für GGUID nicht vorhanden: " . print_r($gwInterestResponse, true));
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unkown_error', 500);
        } else {
            $location_splitted = explode("/", $gwInterestResponse->header('Location'));
            $registerData->personal_gguid = end($location_splitted);
        }
    }

    $now = _getGWNowDate();
    $usernameAndPasswordResponse = createGwUsernameAndPassword($registerData->personal_gguid, $registerData->email, $registerData->password, $now, true);
    if(isError($usernameAndPasswordResponse)) {
        sendErrorNotificationMail('Für den Datensatz ' . $registerData->personal_gguid . ' konnte kein Username und Passwort Verknüpfung erstellt werden.');
    }

    if(App::environment(['production', 'live'])) {
        Mail::to($registerData->email)->send(new RegistrationInterestCustomerMail($registerData));
        Mail::to('vertrieb@trolleymaker.com')->send(new RegistrationInterestMail($registerData));
    }

    return response()->json( $registerData, 200 );
});


Route::get('/interest-complete-personal-data', function (Request $request) {

    if(!$request->input('email')) {
        return returnNewErrorObject('Es wurde keine E-Mail angegeben!', 'no_email', 400);
    }

    $personal_data = getGwPersonalDataByGGUID($request->input('contact_person_gguid'));

    if(isError($personal_data)) {
        return returnErrorObject($personal_data);
    }

    if(!property_exists($personal_data, 'GGUID')) {
        return returnNewErrorObject('Der Benutzer wurde nicht gefunden. Bitte wenden Sie sich an den Support.', 'user_not_found', 500);
    }

    if(!is_array($personal_data) && property_exists($personal_data, 'errorMessage') && !empty($personal_data->errorMessage)) {
        return response()->json( $personal_data, 500 );
    }

    $company_data = getGwPersonalDataByGGUID($personal_data->PRIMARYORGANISATION);

    if(!property_exists($company_data, 'GGUID')) {
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Die Firma wurde nicht gefunden. Bitte kontaktieren Sie den Support.', 'company_not_found', 500);
    }

    if(!property_exists($company_data, 'NCINTERNEID') || empty($company_data->NCINTERNEID) || !property_exists($company_data, 'TMVERTRAGID') || empty($company_data->TMVERTRAGID)) {
        return returnNewErrorObject('Ihre Vertragsnummer wurde nicht gefunden. Bitte wenden Sie sich an den Support!', 'no_contract_id', 500);
    }

    /*
    if(!property_exists($company_data, 'TMGEWERBEVEREININFOMITGLIED') || empty($company_data->TMGEWERBEVEREININFOMITGLIED)) {
        return response()->json( [ 'errorMessage' => 'In Ihrem Account ist keine Info über eine Mitgliedschaft im Gewerbeverein hinterlegt. Bitte wenden Sie sich an den Support!' ], 500 );
    }
    */

    $responseToSend = new stdClass();

    $responseToSend->companyName = $company_data->COMPNAME;
    $responseToSend->companyStreet = $company_data->STREET1;
    $responseToSend->companyZip = $company_data->ZIP1;
    $responseToSend->companyCity = $company_data->TOWN1;
    $responseToSend->companyCountry = $company_data->COUNTRY1;
    $responseToSend->companyGewerbeverein = property_exists($company_data, 'TMGEWERBEVEREININFOMITGLIED') ? $company_data->TMGEWERBEVEREININFOMITGLIED : NULL;
    $responseToSend->companyAddressAdditional = property_exists($company_data, 'GWADDITIONALINFO1') ? $company_data->GWADDITIONALINFO1 : '';
    $responseToSend->community = getShortCommunityString($company_data->TMGEMEINDEZUGEHOERIGKEIT);
    $responseToSend->ceoPhone = property_exists($company_data, 'PHONEFIELDSTR9') ? $company_data->PHONEFIELDSTR9 : '';

    $responseToSend->contactPersonGender = $personal_data->GWGENDER;
    $responseToSend->contactPersonFirstName = $personal_data->CHRISTIANNAME;
    $responseToSend->contactPersonLastName = $personal_data->NAME;
    $responseToSend->contactPersonEmail = $personal_data->TMADMINUSER;

    if(property_exists($company_data, 'TMSPARTNER')) {
        if($company_data->TMSPARTNER == true) {
            $responseToSend->tmspartner = true;
        }
    }

    if(property_exists($company_data, 'TMARTDERSK')) {
        $responseToSend->sktype = '';
        $sktypes = [];

        if(contains('Kein Disagio GutscheinCARD', $company_data->TMARTDERSK)) {
            array_push($sktypes, 'Kein Disagio GutscheinCARD');
        }
        if(contains('Kein Disagio MitarbeiterCARD', $company_data->TMARTDERSK)) {
            array_push($sktypes, 'Kein Disagio MitarbeiterCARD');
        }
        if(contains('Abgeänderte Einrichtungsgebühr GutscheinCARD', $company_data->TMARTDERSK)) {
            array_push($sktypes, 'Abgeänderte Einrichtungsgebühr GutscheinCARD');
        }
        if(contains('Abgeänderte Einrichtungsgebühr MitarbeiterCARD', $company_data->TMARTDERSK)) {
            array_push($sktypes, 'Abgeänderte Einrichtungsgebühr MitarbeiterCARD');
        }
        if(contains('Keine Einrichtungsgebühr GutscheinCARD', $company_data->TMARTDERSK)) {
            array_push($sktypes, 'Keine Einrichtungsgebühr GutscheinCARD');
        }
        if(contains('Keine Einrichtungsgebühr MitarbeiterCARD', $company_data->TMARTDERSK)) {
            array_push($sktypes, 'Keine Einrichtungsgebühr MitarbeiterCARD');
        }
        if(contains('EC-Terminal', $company_data->TMARTDERSK) || contains('EC Terminal', $company_data->TMARTDERSK)) {
            array_push($sktypes, 'EC-Terminal');
        }
        if(count($sktypes) > 0) {
            $responseToSend->sktype = implode(',', $sktypes);
        }
    }

    if(property_exists($company_data, 'TMBEREITGEBUEHRGUTSCHEINCARD') && $company_data->TMBEREITGEBUEHRGUTSCHEINCARD != NULL) {
        $responseToSend->feePartner = $company_data->TMBEREITGEBUEHRGUTSCHEINCARD;
    }

    if(property_exists($company_data, 'TMBEREITGEBUEHRMITARBEITERCARD') && $company_data->TMBEREITGEBUEHRMITARBEITERCARD != NULL) {
        $responseToSend->feeEmployer = $company_data->TMBEREITGEBUEHRMITARBEITERCARD;
    }

    if(property_exists($company_data, 'TMDISAGIOMODELLPARTNER') && $company_data->TMDISAGIOMODELLPARTNER != NULL) {
        $responseToSend->disagio = $company_data->TMDISAGIOMODELLPARTNER;
    }

    return response()->json( $responseToSend, 200 );

})->middleware(['AuthenticateWithSession']);


Route::get('/partner-complete-personal-data', function (Request $request) {

    if(!$request->input('email')) {
        return returnNewErrorObject('Es wurde keine E-Mail angegeben!', 'no_email', 400);
    }

    $personal_data = getGwPersonalDataByGGUID($request->input('contact_person_gguid'));
    $company_data = getGwPersonalDataByGGUID($request->input('company_gguid'));

    if(!property_exists($personal_data, 'GGUID')) {
        return returnNewErrorObject('Der Benutzer wurde nicht gefunden. Bitte wenden Sie sich an den Support.', 'user_not_found', 500);
    }

    if(!property_exists($company_data, 'GGUID')) {
        return returnNewErrorObject('Es wurde keine Firma gefunden. Bitte wenden Sie sich an den Support.', 'company_not_found', 500);
    }

    if(!property_exists($company_data, 'NCINTERNEID') || empty($company_data->NCINTERNEID) || !property_exists($company_data, 'TMVERTRAGID') || empty($company_data->TMVERTRAGID)) {
        return returnNewErrorObject('Ihre Vertragsnummer wurde nicht gefunden. Bitte wenden Sie sich an den Support!', 'company_id_not_found', 500);
    }


    if(!is_array($personal_data) && property_exists($personal_data, 'errorMessage') && !empty($personal_data->errorMessage)) {
        return response()->json( $personal_data, 500 );
    }

    if(!is_array($company_data) && property_exists($company_data, 'errorMessage') && !empty($company_data->errorMessage)) {
        return response()->json( $company_data, 500 );
    }

    if(!property_exists($personal_data, 'TMPARTNERPORTALROLLE')) {
        return returnNewErrorObject('Es wurde keine Benutzerrolle für Ihren Benutzer gefunden. Bitte wenden Sie sich an den Support.', 'no_user_role', 500);
    }

    if(!property_exists($company_data, 'TYPSTANDORT')) {
        return returnNewErrorObject('Bei Ihrer Firma konnte nicht ermittelt werden, ob es sich um eine Zentrale oder Filiale handelt. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
    }

    if(strtolower($company_data->TYPSTANDORT) != 'zentrale' || strtolower($personal_data->TMPARTNERPORTALROLLE) != 'admin') {
        return returnNewErrorObject('Sie haben nicht die nötige Berechtigung, um die Daten der Firma zu ändern. Bitte wenden Sie sich an den Support.', 'unknown_error', 401);
    }


    //get branches
    $gwGetBranches = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->get(env('GW_API_BASE') . '/type/address/full?linked-to=' . $company_data->GGUID . '&linked-to-type=ADDRESS&linked-to-attributes=FILIALEZENTRALE&order-by=INSERTTIMESTAMP');

    if($gwGetBranches->failed()) {
        if($gwGetBranches->status() == 503) {
            return returnNewErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error("Fehler beim Abrufen von gwGetBranches: " . print_r($gwGetBranches->body(), true));
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
        }
    }

    $gwLinkedBranches = json_decode($gwGetBranches);

    $amountOfBranches = count($gwLinkedBranches);

    $responseToSend = new stdClass();
    $responseToSend->contactPersonGender = property_exists($personal_data, 'GWGENDER') ? $personal_data->GWGENDER : '';
    $responseToSend->contactPersonFirstName = $personal_data->CHRISTIANNAME;
    $responseToSend->contactPersonLastName = $personal_data->NAME;
    $responseToSend->contactPersonEmail = $personal_data->TMADMINUSER;
    $responseToSend->originContactPersonEmail = $personal_data->TMADMINUSER;

    $responseToSend->partnerDataComplete = $company_data->TMPARTNERDATENVOLLSTAENDIG;
    $responseToSend->companyName = $company_data->COMPNAME;
    $responseToSend->companyAddressAdditional = property_exists($company_data, 'GWADDITIONALINFO1') ? $company_data->GWADDITIONALINFO1 : '';
    $responseToSend->companyStreet = property_exists($company_data, 'STREET1') ? $company_data->STREET1 : NULL;
    $responseToSend->companyZip = property_exists($company_data, 'ZIP1') ? $company_data->ZIP1 : NULL;
    $responseToSend->companyCity = property_exists($company_data, 'TOWN1') ? $company_data->TOWN1 : NULL;
    $responseToSend->companyCountry = property_exists($company_data, 'COUNTRY1') ? $company_data->COUNTRY1 : NULL;
    $responseToSend->sepaMandateReferenceNumber = $company_data->NCINTERNEID;
    $responseToSend->companyGewerbeverein = property_exists($company_data, 'TMGEWERBEVEREININFOMITGLIED') ? $company_data->TMGEWERBEVEREININFOMITGLIED : NULL;
    $responseToSend->companyEmail = property_exists($company_data, 'MAILFIELDSTR4') ? $company_data->MAILFIELDSTR4 : NULL;
    $responseToSend->companyEmailRepeated = $responseToSend->companyEmail;
    $responseToSend->companyEmailHeadquarter = property_exists($company_data, 'MAILFIELDSTR5') ? $company_data->MAILFIELDSTR5 : $company_data->MAILFIELDSTR4;
    $responseToSend->companyEmailHeadquarterRepeated = $responseToSend->companyEmailHeadquarter;
    $responseToSend->companyREName = property_exists($company_data, 'NCREFIRMA') ? $company_data->NCREFIRMA : $company_data->COMPNAME;
    $responseToSend->companyREZip = property_exists($company_data, 'NCREZIP') ? $company_data->NCREZIP : $company_data->ZIP1;
    $responseToSend->companyREStreet = property_exists($company_data, 'NCRESTREET') ? $company_data->NCRESTREET : $company_data->STREET1;
    $responseToSend->companyRECity = property_exists($company_data, 'NCREORT') ? $company_data->NCREORT : $company_data->TOWN1;
    $responseToSend->companyRECountry = property_exists($company_data, 'TMRELAND') ? $company_data->TMRELAND : $company_data->COUNTRY1;
    $responseToSend->companyREEmail = property_exists($company_data, 'TMMAILRECHNUNG') ? $company_data->TMMAILRECHNUNG : (property_exists($company_data, 'MAILFIELDSTR4') ? $company_data->MAILFIELDSTR4 : NULL);
    $responseToSend->companyREEmailRepeated = $responseToSend->companyREEmail;
    $responseToSend->ceoName = property_exists($company_data, 'TMFIRMENINHABER') ? $company_data->TMFIRMENINHABER : NULL;
    $responseToSend->ceoPhone = property_exists($company_data, 'PHONEFIELDSTR9') ? $company_data->PHONEFIELDSTR9 : NULL;
    if(property_exists($company_data, 'TMDISAGIOMODELLPARTNER')) {
        if(strlen($company_data->TMDISAGIOMODELLPARTNER) < 6) {
            $responseToSend->disagio = getLongDisagioText($company_data->TMDISAGIOMODELLPARTNER);
        } else {
            $responseToSend->disagio = $company_data->TMDISAGIOMODELLPARTNER;
        }
    } else {
        $responseToSend->disagio = NULL;
    }
    $responseToSend->handlingAtPOS = property_exists($company_data, 'TMHANDLINGAMPOS') ? $company_data->TMHANDLINGAMPOS : NULL;
    $responseToSend->smartphoneOS = property_exists($company_data, 'TMBETRIEBSSYSTEM') ? $company_data->TMBETRIEBSSYSTEM : NULL;
    $responseToSend->ecManufacturer = property_exists($company_data, 'TMHERSTELLEREC') ? $company_data->TMHERSTELLEREC : NULL;
    $responseToSend->ecType = property_exists($company_data, 'TMTYPEC') ? $company_data->TMTYPEC : NULL;
    $responseToSend->ecTerminalID = property_exists($company_data, 'TMECTERMINALID') ? $company_data->TMECTERMINALID : NULL;
    $responseToSend->ecSerialNumber = property_exists($company_data, 'TMECTERMINALSERIENNR') ? $company_data->TMECTERMINALSERIENNR : NULL;
    $responseToSend->ecCashpointIntegration = property_exists($company_data, 'TMHANDELKASSENANBINDUNG') ? $company_data->TMHANDELKASSENANBINDUNG : NULL;
    $responseToSend->ecCashpointIntegrationManufacturer = property_exists($company_data, 'TMHANDELSKASSENANBIETER') ? $company_data->TMHANDELSKASSENANBIETER : NULL;
    $responseToSend->cashpointManufacturer = property_exists($company_data, 'TMHANDELSKASSENANBIETER') ? $company_data->TMHANDELSKASSENANBIETER : NULL;
    $responseToSend->paymentMethod = property_exists($company_data, 'NCARTABRECHNUNG') ? $company_data->NCARTABRECHNUNG : NULL;
    $responseToSend->sepaBIC = property_exists($company_data, 'GWBIC') ? $company_data->GWBIC : NULL;
    $responseToSend->sepaIBAN = property_exists($company_data, 'GWIBAN') ? $company_data->GWIBAN : NULL;
    $responseToSend->sepaBankName = property_exists($company_data, 'FINANCIALINSTITUTE') ? $company_data->FINANCIALINSTITUTE : NULL;
    $responseToSend->sepaAccountHolder = property_exists($company_data, 'BANKACCOUNTHOLDER') ? $company_data->BANKACCOUNTHOLDER : NULL;
    $responseToSend->sepaCompanyStreet = $responseToSend->companyREStreet;
    $responseToSend->sepaCompanyZip = $responseToSend->companyREZip;
    $responseToSend->sepaCompanyCity = $responseToSend->companyRECity;
    $responseToSend->contactDetailsSentAt = property_exists($company_data, 'TMEINGANGAUFTRAGPARTNER') ? gWDateToGermanDateAndTime($company_data->TMEINGANGAUFTRAGPARTNER) : '-';
    $responseToSend->systemOnboardingAt = property_exists($company_data, 'TMEINWEISUNGDATUM') ? gWDateToGermanDate($company_data->TMEINWEISUNGDATUM) : '-';
    $responseToSend->contractStartAt = property_exists($company_data, 'TMVERTRAGSDATUM') ? gWDateToGermanDate($company_data->TMVERTRAGSDATUM) : '-';
    $responseToSend->contractEndAt = property_exists($company_data, 'TMVERTRAGSENDE') ? gWDateToGermanDate($company_data->TMVERTRAGSENDE) : '-';
    $responseToSend->contractStatus = property_exists($company_data, 'TMVERTRAGSSTATUSPARTNER') ? $company_data->TMVERTRAGSSTATUSPARTNER : '-';
    $responseToSend->contractID = property_exists($company_data, 'TMVERTRAGID') ? $company_data->TMVERTRAGID : '-';
    $responseToSend->participationFee = property_exists($company_data, 'TMBEREITGEBUEHRGUTSCHEINCARD') ? $company_data->TMBEREITGEBUEHRGUTSCHEINCARD : '-';
    $responseToSend->participationPaymentMethod = property_exists($company_data, 'PAYMENT') ? $company_data->PAYMENT : '-';

    $responseToSend->community = property_exists($company_data, 'TMGEMEINDEZUGEHOERIGKEIT') ? $company_data->TMGEMEINDEZUGEHOERIGKEIT : NULL;

    if(property_exists($company_data, 'TMSPARTNER')) {
        if($company_data->TMSPARTNER == true) {
            $responseToSend->tmspartner = true;
        }
    }

    if(property_exists($company_data, 'TMARTDERSK')) {
        $responseToSend->sktype = '';
        $sktypes = [];

        if(contains('Kein Disagio GutscheinCARD', $company_data->TMARTDERSK)) {
            array_push($sktypes, 'Kein Disagio GutscheinCARD');
        }
        if(contains('Kein Disagio MitarbeiterCARD', $company_data->TMARTDERSK)) {
            array_push($sktypes, 'Kein Disagio MitarbeiterCARD');
        }
        if(contains('Abgeänderte Einrichtungsgebühr GutscheinCARD', $company_data->TMARTDERSK)) {
            array_push($sktypes, 'Abgeänderte Einrichtungsgebühr GutscheinCARD');
        }
        if(contains('Abgeänderte Einrichtungsgebühr MitarbeiterCARD', $company_data->TMARTDERSK)) {
            array_push($sktypes, 'Abgeänderte Einrichtungsgebühr MitarbeiterCARD');
        }
        if(contains('Keine Einrichtungsgebühr GutscheinCARD', $company_data->TMARTDERSK)) {
            array_push($sktypes, 'Keine Einrichtungsgebühr GutscheinCARD');
        }
        if(contains('Keine Einrichtungsgebühr MitarbeiterCARD', $company_data->TMARTDERSK)) {
            array_push($sktypes, 'Keine Einrichtungsgebühr MitarbeiterCARD');
        }
        if(contains('EC-Terminal', $company_data->TMARTDERSK) || contains('EC Terminal', $company_data->TMARTDERSK)) {
            array_push($sktypes, 'EC-Terminal');
        }
        if(count($sktypes) > 0) {
            $responseToSend->sktype = implode(',', $sktypes);
        }
    }

    $responseToSend->branches = array();

    //prepend $company_data to $gwLinkedBranches
    array_unshift($gwLinkedBranches, $company_data);

    $i = 0;

    foreach ($gwLinkedBranches as $branch) {

        if($i == 0) {
            $branch_company_data = $branch;
            $branch_company_data->COMPNAME2 = property_exists($branch_company_data, 'COMPNAME2') ? $branch_company_data->COMPNAME2 : $branch_company_data->COMPNAME;
            $branch_company_data->STREET2 = property_exists($branch_company_data, 'STREET2') ? $branch_company_data->STREET2 : $branch_company_data->STREET1;
            $branch_company_data->TOWN2 = property_exists($branch_company_data, 'TOWN2') ? $branch_company_data->TOWN2 : $branch_company_data->TOWN1;
            $branch_company_data->ZIP2 = property_exists($branch_company_data, 'ZIP2') ? $branch_company_data->ZIP2 : $branch_company_data->ZIP1;
            $branch_company_data->COUNTRY2 = property_exists($branch_company_data, 'COUNTRY2') ? $branch_company_data->COUNTRY2 : $branch_company_data->COUNTRY1;
        } else {
            $branch_company_data = $branch->fields;
        }

        if(!property_exists($branch_company_data, 'GGUID')) {
            Log::error("Fehler beim Abrufen von Filiale " . $branch->fields->GGUID);
            return returnNewErrorObject('Die Daten einer oder mehrere Filialen konnten nicht abgerufen werden. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
        }


        $branchCompanyData = $branch_company_data->GGUID;

        //get branch user
        $gwBranchUsers = getGwBranchUsers($branch_company_data->GGUID);


        if(!is_array($gwBranchUsers) && property_exists($gwBranchUsers, 'errorMessage') && !empty($gwBranchUsers->errorMessage)) {
            return response()->json( $gwBranchUsers, 500 );
        }


        $tempBranch = new stdClass();
        $tempBranch->isOriginBranch = true;
        $tempBranch->companyNameIntern = property_exists($branch_company_data, 'TMINTERNEBEZEICHNUNG') ? $branch_company_data->TMINTERNEBEZEICHNUNG : '';

        $tempBranch->companyName = property_exists($branch_company_data, 'COMPNAME2') ? $branch_company_data->COMPNAME2 : '';
        $tempBranch->companyStreet = property_exists($branch_company_data, 'STREET2') ? $branch_company_data->STREET2 : '';
        $tempBranch->companyCity = property_exists($branch_company_data, 'TOWN2') ? $branch_company_data->TOWN2 : '';
        $tempBranch->companyZip = property_exists($branch_company_data, 'ZIP2') ? $branch_company_data->ZIP2 : '';
        $tempBranch->companyCountry = property_exists($branch_company_data, 'COUNTRY2') ? $branch_company_data->COUNTRY2 : '';
        $tempBranch->companyWebsite = property_exists($branch_company_data, 'WWWFIELDSTR1') ? $branch_company_data->WWWFIELDSTR1 : '';
        $tempBranch->companyEmail = property_exists($branch_company_data, 'TMMAILVEROEFFENTLICHUNG') ? $branch_company_data->TMMAILVEROEFFENTLICHUNG : '';
        $tempBranch->companyPhone = property_exists($branch_company_data, 'TMPHONEVEROEFFENTLICHUNG') ? $branch_company_data->TMPHONEVEROEFFENTLICHUNG : '';

        if(property_exists($branch_company_data, 'CATEGORY')) {
            $categories = explode(", ", $branch_company_data->CATEGORY);
            $tempBranch->companyCategories = $categories;
        } else {
            $tempBranch->companyCategories = [];
        }

        $tempBranch->companyOpenHoursOnlyByArrangement = $branch_company_data->TMTERMINVEREINBARUNG;
        $tempBranch->isClosedOnMonday = $branch_company_data->TMPARTNERHATGESCHLOSSENMO;
        $tempBranch->isClosedOnTuesday = $branch_company_data->TMPARTNERHATGESCHLOSSENDI;
        $tempBranch->isClosedOnWednesday = $branch_company_data->TMPARTNERHATGESCHLOSSENMI;
        $tempBranch->isClosedOnThursday = $branch_company_data->TMPARTNERHATGESCHLOSSENDO;
        $tempBranch->isClosedOnFriday = $branch_company_data->TMPARTNERHATGESCHLOSSENFR;
        $tempBranch->isClosedOnSaturday = $branch_company_data->TMPARTNERHATGESCHLOSSENSA;
        $tempBranch->isClosedOnSunday = $branch_company_data->TMPARTNERHATGESCHLOSSENSO;
        $tempBranch->companyOpenHoursMondayFrom1 = property_exists($branch_company_data, 'TMOEFFZEITMONTAG1VON') ? $branch_company_data->TMOEFFZEITMONTAG1VON : '';
        $tempBranch->companyOpenHoursMondayFrom2 = property_exists($branch_company_data, 'TMOEFFZEITMONTAG2VON') ? $branch_company_data->TMOEFFZEITMONTAG2VON : '';
        $tempBranch->companyOpenHoursMondayTo1 = property_exists($branch_company_data, 'TMOEFFZEITMONTAG1BIS') ? $branch_company_data->TMOEFFZEITMONTAG1BIS : '';
        $tempBranch->companyOpenHoursMondayTo2 = property_exists($branch_company_data, 'TMOEFFZEITMONTAG2BIS') ? $branch_company_data->TMOEFFZEITMONTAG2BIS : '';
        $tempBranch->companyOpenHoursTuesdayFrom1 = property_exists($branch_company_data, 'TMOEFFZEITDIENSTAG1VON') ? $branch_company_data->TMOEFFZEITDIENSTAG1VON : '';
        $tempBranch->companyOpenHoursTuesdayFrom2 = property_exists($branch_company_data, 'TMOEFFZEITDIENSTAG2VON') ? $branch_company_data->TMOEFFZEITDIENSTAG2VON : '';
        $tempBranch->companyOpenHoursTuesdayTo1 = property_exists($branch_company_data, 'TMOEFFZEITDIENSTAG1BIS') ? $branch_company_data->TMOEFFZEITDIENSTAG1BIS : '';
        $tempBranch->companyOpenHoursTuesdayTo2 = property_exists($branch_company_data, 'TMOEFFZEITDIENSTAG2BIS') ? $branch_company_data->TMOEFFZEITDIENSTAG2BIS : '';
        $tempBranch->companyOpenHoursWednesdayFrom1 = property_exists($branch_company_data, 'TMOEFFZEITMITTWOCH1VON') ? $branch_company_data->TMOEFFZEITMITTWOCH1VON : '';
        $tempBranch->companyOpenHoursWednesdayFrom2 = property_exists($branch_company_data, 'TMOEFFZEITMITTWOCH2VON') ? $branch_company_data->TMOEFFZEITMITTWOCH2VON : '';
        $tempBranch->companyOpenHoursWednesdayTo1 = property_exists($branch_company_data, 'TMOEFFZEITMITTWOCH1BIS') ? $branch_company_data->TMOEFFZEITMITTWOCH1BIS : '';
        $tempBranch->companyOpenHoursWednesdayTo2 = property_exists($branch_company_data, 'TMOEFFZEITMITTWOCH2BIS') ? $branch_company_data->TMOEFFZEITMITTWOCH2BIS : '';
        $tempBranch->companyOpenHoursThursdayFrom1 = property_exists($branch_company_data, 'TMOEFFZEITDONNERSTAG1VON') ? $branch_company_data->TMOEFFZEITDONNERSTAG1VON : '';
        $tempBranch->companyOpenHoursThursdayFrom2 = property_exists($branch_company_data, 'TMOEFFZEITDONNERSTAG2VON') ? $branch_company_data->TMOEFFZEITDONNERSTAG2VON : '';
        $tempBranch->companyOpenHoursThursdayTo1 = property_exists($branch_company_data, 'TMOEFFZEITDONNERSTAG1BIS') ? $branch_company_data->TMOEFFZEITDONNERSTAG1BIS : '';
        $tempBranch->companyOpenHoursThursdayTo2 = property_exists($branch_company_data, 'TMOEFFZEITDONNERSTAG2BIS') ? $branch_company_data->TMOEFFZEITDONNERSTAG2BIS : '';
        $tempBranch->companyOpenHoursFridayFrom1 = property_exists($branch_company_data, 'TMOEFFZEITFREITAG1VON') ? $branch_company_data->TMOEFFZEITFREITAG1VON : '';
        $tempBranch->companyOpenHoursFridayFrom2 = property_exists($branch_company_data, 'TMOEFFZEITFREITAG2VON') ? $branch_company_data->TMOEFFZEITFREITAG2VON : '';
        $tempBranch->companyOpenHoursFridayTo1 = property_exists($branch_company_data, 'TMOEFFZEITFREITAG1BIS') ? $branch_company_data->TMOEFFZEITFREITAG1BIS : '';
        $tempBranch->companyOpenHoursFridayTo2 = property_exists($branch_company_data, 'TMOEFFZEITFREITAG2BIS') ? $branch_company_data->TMOEFFZEITFREITAG2BIS : '';
        $tempBranch->companyOpenHoursSaturdayFrom1 = property_exists($branch_company_data, 'TMOEFFZEITSAMSTAG1VON') ? $branch_company_data->TMOEFFZEITSAMSTAG1VON : '';
        $tempBranch->companyOpenHoursSaturdayFrom2 = property_exists($branch_company_data, 'TMOEFFZEITSAMSTAG2VON') ? $branch_company_data->TMOEFFZEITSAMSTAG2VON : '';
        $tempBranch->companyOpenHoursSaturdayTo1 = property_exists($branch_company_data, 'TMOEFFZEITSAMSTAG1BIS') ? $branch_company_data->TMOEFFZEITSAMSTAG1BIS : '';
        $tempBranch->companyOpenHoursSaturdayTo2 = property_exists($branch_company_data, 'TMOEFFZEITSAMSTAG2BIS') ? $branch_company_data->TMOEFFZEITSAMSTAG2BIS : '';
        $tempBranch->companyOpenHoursSundayFrom1 = property_exists($branch_company_data, 'TMOEFFZEITSONNTAG1VON') ? $branch_company_data->TMOEFFZEITSONNTAG1VON : '';
        $tempBranch->companyOpenHoursSundayFrom2 = property_exists($branch_company_data, 'TMOEFFZEITSONNTAG2VON') ? $branch_company_data->TMOEFFZEITSONNTAG2VON : '';
        $tempBranch->companyOpenHoursSundayTo1 = property_exists($branch_company_data, 'TMOEFFZEITSONNTAG1BIS') ? $branch_company_data->TMOEFFZEITSONNTAG1BIS : '';
        $tempBranch->companyOpenHoursSundayTo2 = property_exists($branch_company_data, 'TMOEFFZEITSONNTAG2BIS') ? $branch_company_data->TMOEFFZEITSONNTAG2BIS : '';
        $tempBranch->companyOpenHoursAdditionalInfo = property_exists($branch_company_data, 'TMINFOOEFFNUNGSZEIT') ? $branch_company_data->TMINFOOEFFNUNGSZEIT : '';

        foreach ($gwBranchUsers as $branchUser) {
            if(property_exists($branchUser, 'TMPARTNERPORTALROLLE') && strtolower($branchUser->TMPARTNERPORTALROLLE) != 'keine' && $branchUser->TMPARTNERPORTALROLLE != '') {
                $tempBranchUser = new stdClass();
                $tempBranchUser->contactPersonGender = property_exists($branchUser, 'GWGENDER') ? $branchUser->GWGENDER : NULL;
                $tempBranchUser->contactPersonFirstName = $branchUser->CHRISTIANNAME;
                $tempBranchUser->contactPersonLastName = $branchUser->NAME;
                $tempBranchUser->contactPersonEmail = $branchUser->TMADMINUSER;
                $tempBranchUser->contactPersonEmailRepeated = $branchUser->TMADMINUSER;
                $tempBranchUser->originContactPersonEmail = $branchUser->TMADMINUSER;
                $tempBranchUser->contactPersonPartnerPortalRole = $branchUser->TMPARTNERPORTALROLLE;

                if(!property_exists($tempBranch, 'branchUsers')) {
                    $tempBranch->branchUsers = array();
                }
                $tempBranch->branchUsers[] = $tempBranchUser;
            }
        }
        $tempBranch->branchUserIndex = 0;
        if(property_exists($tempBranch, 'branchUsers') && count($tempBranch->branchUsers) > 0) {
            $tempBranch->branchUserIndex = count($tempBranch->branchUsers) - 1;
        }

        $responseToSend->branches[] = $tempBranch;
        $i++;
    }

    return response()->json( $responseToSend, 200 );

})->middleware(['AuthenticateWithSession', 'AuthenticateIsPartnerAdmin']);


//can be for both: partner and employer registration
Route::post('/partner-registration', function (Request $request) {

    foreach(array('ceoName', 'ceoPhone', 'companyName', 'companyStreet', 'companyZip', 'companyCity', 'companyCountry', 'companyEmail', 'companyEmailRepeated', 'contactPersonEmail') as $input) {
        if(!$request->has($input) || $request->input($input) == NULL || $request->input($input) == '') {
            return returnNewErrorObject('Es wurden nicht alle erforderlichen Felder ausgefüllt!', 'missing_fields', 400);
        }
    }

    $registerData = new stdClass();
    $registerData->TMADMINUSER = trim($request->input('contactPersonEmail'));

    $registerData->companyCountry = $request->input('companyCountry');
    if($request->has('isAlsoEmployer') && $request->input('isAlsoEmployer') == true) {
        foreach(array('employerLoginEmail', 'employerLoginEmailRepeated', 'employerPassword', 'employerPassword') as $input) {
            if(!$request->has($input) || $request->input($input) == NULL || $request->input($input) == '') {
                return returnNewErrorObject('Es wurden nicht alle erforderlichen Felder ausgefüllt: ' . $input, 'missing_required_fields', 400);
            }
        }

        if($request->input('employerLoginEmail') != $request->input('employerLoginEmailRepeated')) {
            return returnNewErrorObject('Die beiden E-Mail-Adressen für den Arbeitgeber-Login stimmen nicht überein!', 'email_repeated_error', 400);
        } else {
            $registerData->employerLoginEmail = trim($request->input('employerLoginEmail'));
        }

        if(strlen($request->input('employerPassword')) > 50) {
            return returnNewErrorObject('Das Passwort darf maximal 50 Zeichen lang sein!', 'password_invalid', 400);
        }

        if($request->input('employerPassword') != $request->input('employerPasswordRepeated')) {
            return returnNewErrorObject('Die beiden Passwörter stimmen nicht überein!' , 'password_repeated_error', 400);
        } else {
            $registerData->employerPassword = $request->input('employerPassword');
        }

        $checkInValueMasterIfEmployerEmailAlreadyExists = Http::withHeaders([
            'provider' => 'trolleymaker',
            'password' => 'poiJJ#9q9'
        ])->post('https://valuemaster.brain-behind.com/CU_WebAPI.asmx/CU_checkIfCustomerExists', [
            'searchKey' =>  'E-Mail',
            'searchKeyvalue' =>  $registerData->employerLoginEmail
        ]);

        if($checkInValueMasterIfEmployerEmailAlreadyExists->failed() || $checkInValueMasterIfEmployerEmailAlreadyExists == NULL) {
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Es konnte nicht geprüft werden, ob die E-Mail-Adresse bereits registriert wurde. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
        }

        if($checkInValueMasterIfEmployerEmailAlreadyExists && $checkInValueMasterIfEmployerEmailAlreadyExists != NULL) {
            $exists_data = json_decode($checkInValueMasterIfEmployerEmailAlreadyExists)->d;

            if($exists_data && $exists_data != NULL) {
                if(property_exists($exists_data, 'exists') && $exists_data->exists == true) {
                    return returnNewErrorObject('Es wurde bereits ein Account mit der Arbeitgeber-E-Mail-Adresse registriert. Bitte benutzen Sie eine andere E-Mail-Adresse.', 'account_already_exists', 400);
                }
            } else {
                return returnNewErrorObject('Es wurde bereits ein Account mit der Arbeitgeber-E-Mail-Adresse registriert. Bitte benutzen Sie eine andere E-Mail-Adresse.', 'account_already_exists', 400);
            }
        } else {
            return returnNewErrorObject('Es wurde bereits ein Account mit der Arbeitgeber-E-Mail-Adresse registriert. Bitte benutzen Sie eine andere E-Mail-Adresse.', 'account_already_exists', 400);
        }

        $checkIfEmployerEmailAlreadyRegistered = Http::withHeaders([
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
            'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
        ])->post(env('GW_API_BASE') . '/query', [
            'query' => 'SELECT NAME FROM address WHERE TMADMINUSER="' . $registerData->employerLoginEmail. '" OR (MAILFIELDSTR3="' . $registerData->employerLoginEmail . '" AND GWSTYPE="Kunde")'
        ]);

        if($checkIfEmployerEmailAlreadyRegistered->failed()) {
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Es konnte nicht geprüft werden, ob die Arbeitgeber-E-Mail-Adresse bereits verwendet wird. Bitte wenden Sie sich an den Support.', 'unknown_error', 400);
        }

        $employerDataAlreadyRegistered = json_decode($checkIfEmployerEmailAlreadyRegistered);
        if($employerDataAlreadyRegistered && count($employerDataAlreadyRegistered) > 0) {
            return returnNewErrorObject('Es wurde bereits ein Partner- oder Kunden-Account mit der angegebenen Arbeitgeber-E-Mail-Adresse registriert.', 'account_already_exists', 400);
        }
    }

    if($request->input('companyEmail') != $request->input('companyEmailRepeated')) {
        return returnNewErrorObject('Die beiden E-Mail Adressen stimmen nicht überein!', 'email_repeated_error', 400);
    } else {
        $registerData->companyEmail = trim($request->input('companyEmail'));
        $registerData->email = trim($request->input('companyEmail'));
    }



    $interest_personal_data = getGwInterestAndPartnerPersonalData('GGUID, PRIMARYORGANISATION, NCREGION, NCINTERNEID, GWGENDER, CHRISTIANNAME, NAME, TMADMINUSER, NCINTERESSENTPWD, NCORTDERANMELDUNG, TMADMINUSERROLLE, COUNTRY1', $registerData->TMADMINUSER, true, false);

    if(!is_array($interest_personal_data) && property_exists($interest_personal_data, 'errorMessage') && !empty($interest_personal_data->errorMessage)) {
        return returnErrorObject($interest_personal_data);
    }

    $company_data = getGwPersonalDataByGGUID($interest_personal_data->PRIMARYORGANISATION);

    if(!property_exists($company_data, 'GGUID')) {
        return returnNewErrorObject('Es wurde keine Firma zu dem Ansprechpartner gefunden. Bitte wenden Sie sich an den Support.', 'no_company_found', 500);
    }


    $isRegionWithoutValueMaster = in_array($interest_personal_data->NCREGION, config('newRegions.regions_without_valuemaster'));

    if(!$isRegionWithoutValueMaster) {
        $checkInValueMasterIfEmailAlreadyExists = Http::withHeaders([
            'provider' => 'trolleymaker',
            'password' => 'poiJJ#9q9'
        ])->post('https://valuemaster.brain-behind.com/CU_WebAPI.asmx/CU_checkIfCustomerExists', [
            'searchKey' =>  'E-Mail',
            'searchKeyvalue' => $registerData->TMADMINUSER
        ]);

        if($checkInValueMasterIfEmailAlreadyExists->failed() || $checkInValueMasterIfEmailAlreadyExists == NULL) {
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Es konnte nicht geprüft werden, ob die E-Mail-Adresse bereits registriert wurde. Bitte wenden Sie sich an den Support.', 'could_not_check_email_exists', 500);
        }

        if($checkInValueMasterIfEmailAlreadyExists && $checkInValueMasterIfEmailAlreadyExists != NULL) {
            $exists_data = json_decode($checkInValueMasterIfEmailAlreadyExists)->d;

            if($exists_data && $exists_data != NULL) {
                if(property_exists($exists_data, 'exists') && $exists_data->exists === true) {
                    return returnNewErrorObject('Es wurde bereits ein Account mit der Firmen-E-Mail-Adresse registriert. Bitte benutzen Sie eine andere E-Mail-Adresse.', 'email_already_exists', 400);
                }
            } else {
                return returnNewErrorObject('Es ist ein Fehler aufgetreten. Es konnte nicht geprüft werden, ob die E-Mail-Adresse bereits registriert wurde. Bitte wenden Sie sich an den Support.', 'could_not_check_email_exists', 500);
            }
        } else {
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Es konnte nicht geprüft werden, ob die E-Mail-Adresse bereits registriert wurde. Bitte wenden Sie sich an den Support.', 'could_not_check_email_exists', 500);
        }
    }


    if(strlen($request->input('companyZip')) == 4 || strlen($request->input('companyZip')) == 5) {
        $registerData->companyZip = $request->input('companyZip');
    } else {
        return returnNewErrorObject('Die Postleitzahl darf nur aus 4 oder 5 Zahlen bestehen.', 'zip_invalid', 400);
    }

    $countryForVM = $request->input('companyCountry');

    $registerData->companyName = trim($request->input('companyName'));
    $registerData->companyStreet = trim($request->input('companyStreet'));
    $registerData->companyCity = trim($request->input('companyCity'));
    $registerData->contactPersonRole = trim($request->input('contactPersonRole'));
    if(property_exists($company_data, 'TMARTDERSK') && contains('Kein Disagio', $company_data->TMARTDERSK)) {
        $registerData->disagio = "0% - Sondertarif";
    } else if($company_data->TMGEMEINDEZUGEHOERIGKEIT == 'Lahr') {
        if(!$request->has('disagio') || empty($request->input('disagio'))) {
            return returnNewErrorObject('Es wurde kein Disagio Modell angegeben!', 'no_disagio', 400);
        }

        $disagioFromRequest = trim($request->input('disagio'));
        if(strlen($disagioFromRequest) < 6) {
            $registerData->disagio = getLongDisagioText($disagioFromRequest);
        } else {
            $registerData->disagio = $disagioFromRequest;
        }
    } else {
        $registerData->disagio = "2% - keine Teilnahmegebühr";
    }
    $registerData->ceoName = trim($request->input('ceoName'));
    $registerData->ceoPhone = trim($request->input('ceoPhone'));
    $registerData->contactPersonFirstName = $interest_personal_data->CHRISTIANNAME;
    $registerData->contactPersonLastName = $interest_personal_data->NAME;
    $registerData->cardName = $interest_personal_data->NCORTDERANMELDUNG;
    if(!property_exists($interest_personal_data, 'GWGENDER' || empty($interest_personal_data->GWGENDER))) {
        $interest_personal_data->GWGENDER = '';
    }
    $registerData->gender = $interest_personal_data->GWGENDER;

    if($request->has('companyAddressAdditional')) {
        $registerData->companyAddressAdditional = $request->input('companyAddressAdditional');
    } else {
        $registerData->companyAddressAdditional = '';
    }

    if($request->has('priceToPay')) {
        $registerData->priceToPay = $request->input('priceToPay');
    } else {
        $registerData->priceToPay = '';
    }

    if($request->has('isAlsoEmployer') && $request->input('isAlsoEmployer') == true) {
        $registerData->contractBundle = true;
    } else {
        $registerData->contractBundle = false;
    }

    $partnerCompanyID = '';

    if(!$isRegionWithoutValueMaster) {
        $randomPassword = generateRandomPassword();

        $registerPartnerInValueMaster = Http::withHeaders([
            'provider' => 'trolleymaker',
            'password' => 'poiJJ#9q9'
        ])->post('https://valuemaster.brain-behind.com/CU_WebAPI.asmx/CU_Add_Modify_Partner', [
            'CompanyName' =>  $registerData->companyName,
            'CompanyID' => 0,
            'active' => '1',
            'InternalID' => $company_data->NCINTERNEID,
            'BusinessSector' => array(),
            'PhoneNumer' => '',
            'Street' => $registerData->companyStreet,
            'ZIP' => $registerData->companyZip,
            'City' => $registerData->companyCity,
            'Country' => $countryForVM,
            'Language' => 'de',
            'ReceiveStats' => true,
            'ShowPartner' => true,
            'ReceiveInvoice' => true,
            'ChargeTX' => true,
            'CompanyEmail' => $registerData->companyEmail,
            'Web' => '',
            'BankName' => '',
            'IBAN' => '',
            'BIC' => '',
            'latitude' => 0,
            'longitute' => 0,
            'CompanyNameOnInvoice' => '',
            'CompanyContactPersonOnInvoice' => '',
            'InvoiceStreet' => '',
            'InvoiceZIP' => '',
            'InvoiceCity' => '',
            'InvoiceMail' => '',
            'VATID' => '',
            'logo' => null,
            'Category' => array(),
            'RuleSET' => '',
            'Payment' => 'SEPA_DirectDebit',
            'Admin_User' => [
                'Sex' => $interest_personal_data->GWGENDER,
                'PreName' => $interest_personal_data->CHRISTIANNAME,
                'Name' => $interest_personal_data->NAME,
                'LoginEmail' => $registerData->TMADMINUSER,
                'Password' => $randomPassword,
                'SendWelcomeMail' => false
            ]
        ]);

        if($registerPartnerInValueMaster->failed() || $registerPartnerInValueMaster == NULL) {
            Log::Error('Registrierung des Interessenten zum Partner ist im ValueMaster fehlgeschlagen: ' . $registerPartnerInValueMaster->body());
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Partner-Account konnte nicht erstellt werden. Bitte wenden Sie sich an den Support.', 'unkown_error', 500);
        }

        if($registerPartnerInValueMaster && $registerPartnerInValueMaster != NULL) {
            $partnerDataFromValueMaster = json_decode($registerPartnerInValueMaster)->d;

            if($partnerDataFromValueMaster && $partnerDataFromValueMaster != NULL) {
                if(!property_exists($partnerDataFromValueMaster, 'status') || strtolower($partnerDataFromValueMaster->status) != 'ok' || !empty($partnerDataFromValueMaster->error)) {
                    Log::Error('Registrierung des Interessenten zum Partner ist im ValueMaster fehlgeschlagen: ' . $registerPartnerInValueMaster->body());
                    return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Partner-Account konnte nicht erstellt werden. Bitte wenden Sie sich an den Support.', 'unkown_error', 500);
                }
                if(!property_exists($partnerDataFromValueMaster, 'CompanyID') || empty($partnerDataFromValueMaster->CompanyID)) {
                    Log::Error('Registrierung des Interessenten zum Partner ist im ValueMaster fehlgeschlagen: ' . $registerPartnerInValueMaster->body());
                    return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Partner-Account konnte nicht erstellt werden. Bitte wenden Sie sich an den Support.', 'unkown_error', 500);
                }
            } else {
                Log::Error('Registrierung des Interessenten zum Partner ist im ValueMaster fehlgeschlagen: ' . $registerPartnerInValueMaster->body());
                return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Partner-Account konnte nicht erstellt werden. Bitte wenden Sie sich an den Support.', 'unkown_error', 500);
            }
        } else {
            Log::Error('Registrierung des Interessenten zum Partner ist im ValueMaster fehlgeschlagen: ' . $registerPartnerInValueMaster->body());
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Partner-Account konnte nicht erstellt werden. Bitte wenden Sie sich an den Support.', 'unkown_error', 500);
        }


        /*
        $terminalGroup2 = new \StdClass();
        $terminalGroup2->terminalGroupId = '1212002';
        $terminalGroup2->consumer = '';
        $terminalGroup2->producer = '';

        $terminalGroup3 = new \StdClass();
        $terminalGroup3->terminalGroupId = $terminalgroupid_gutschein;
        $terminalGroup3->consumer = '';
        $terminalGroup3->producer = '';

        $terminalGroup4 = new \StdClass();
        $terminalGroup4->terminalGroupId = $terminalgroupid_mitarbeitercard;
        $terminalGroup4->consumer = '';
        $terminalGroup4->producer = '';

        $terminalGroups = [$terminalGroup1, $terminalGroup2, $terminalGroup3, $terminalGroup4];

        foreach ($terminalGroups as $terminalGroup) {
            $addTerminal = Http::withHeaders([
                'provider' => 'trolleymaker',
                'password' => 'poiJJ#9q9'
            ])->post('https://valuemaster.brain-behind.com/CU_WebAPI.asmx/CU_Add_Terminal', [
                'CompanyID' =>  $partnerDataFromValueMaster->CompanyID,
                'BranchID' => 0,
                'TerminalID"' => 'Webterminal_' . $partnerDataFromValueMaster->CompanyID,
                'TerminalGroup"' => $terminalGroup->terminalGroupId,
                'Consumer"' => $terminalGroup->consumer,
                'Producer"' => $terminalGroup->producer
            ]);

            if($addTerminal->failed() || $addTerminal == NULL) {
                Log::Error('Registrierung des Interessenten zum Partner ist im ValueMaster bei CU_ADD_TERMINAL fehlgeschlagen: ' . $addTerminal->body());
                return response()->json( [ 'errorMessage' => 'Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.' ], 500 );
            }
        }
        */

        $addTerminalResponse = addTerminal(intval($partnerDataFromValueMaster->CompanyID), intval($partnerDataFromValueMaster->BranchID));
        if(isError($addTerminalResponse)) {
            Log::error('Das Terminal konnte nicht angelegt werden.');
            sendErrorNotificationMail('Das Terminal für FirmenID: ' . $partnerDataFromValueMaster->CompanyID . ' und BranchID: ' . $partnerDataFromValueMaster->BranchID . ' konnte nicht angelegt werden.');
        }

        $partnerCompanyID = strval($partnerDataFromValueMaster->CompanyID);
    } else {
        $generatePartnerIdResponse = Http::withHeaders([
            'X-API-Key' => config('newRegions.go_backend_api_key'),
        ])->get(config('newRegions.go_backend_url') . '/portals/api/v1/partners/generate-id');

        if($generatePartnerIdResponse->failed() || $generatePartnerIdResponse == NULL) {
            Log::Error('Partner-ID konnte nicht generiert werden: ' . ($generatePartnerIdResponse ? $generatePartnerIdResponse->body() : 'NULL'));
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Partner-Account konnte nicht erstellt werden. Bitte wenden Sie sich an den Support.', 'partner_id_generation_failed', 500);
        }

        $partnerIdData = json_decode($generatePartnerIdResponse);
        if(!$partnerIdData || !property_exists($partnerIdData, 'partnerId') || empty($partnerIdData->partnerId)) {
            Log::Error('Partner-ID konnte nicht aus der Antwort gelesen werden: ' . $generatePartnerIdResponse->body());
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Partner-Account konnte nicht erstellt werden. Bitte wenden Sie sich an den Support.', 'partner_id_generation_failed', 500);
        }

        $partnerCompanyID = strval($partnerIdData->partnerId);
    }



    $dateNow = new DateTime('now');
    $dateNow->setTimezone(new DateTimeZone('Europe/Berlin'));
    $registerData->registeredSince = $dateNow->format('Y-m-d\TH:i:s');

    $fieldsToUpdate = new stdClass();
    $fieldsToUpdate->GWSTYPE = 'Partnerschaft';
    $fieldsToUpdate->TMARTDERPARTNERSCHAFT = "Partner";
    $fieldsToUpdate->TMMODULEPARTNER = 'GutscheinCARD';
    $fieldsToUpdate->MAILFIELDSTR4 = $registerData->companyEmail;
    $fieldsToUpdate->TMFIRMENINHABER = $registerData->ceoName;
    $fieldsToUpdate->PHONEFIELDSTR9 = $registerData->ceoPhone;
    $fieldsToUpdate->TMDISAGIOMODELLPARTNER = $registerData->disagio;
    $fieldsToUpdate->GWADDITIONALINFO1 = $registerData->companyAddressAdditional;
    $fieldsToUpdate->STREET1 = $registerData->companyStreet;
    $fieldsToUpdate->NCRESTREET = $registerData->companyStreet;
    $fieldsToUpdate->TOWN1 = $registerData->companyCity;
    $fieldsToUpdate->NCREORT = $registerData->companyCity;
    $fieldsToUpdate->ZIP1 = $registerData->companyZip;
    $fieldsToUpdate->NCREZIP = $registerData->companyZip;
    $fieldsToUpdate->COUNTRY1 = $registerData->companyCountry;
    $fieldsToUpdate->TMRELAND = $registerData->companyCountry;
    $fieldsToUpdate->COMPNAME = $registerData->companyName;
    $fieldsToUpdate->NCREFIRMA = $registerData->companyName;
    $fieldsToUpdate->NCINTERESSENTPWD = NULL;
    $fieldsToUpdate->NCFIRMENID = $partnerCompanyID;
    $fieldsToUpdate->TMBETRAGVERTRAGSABSCHLUSS = intval($registerData->priceToPay);
    $fieldsToUpdate->TMVERTRAGSBUNDLE = $registerData->contractBundle;
    $fieldsToUpdate->TMEINGANGAUFTRAGPARTNER = $registerData->registeredSince;
    $fieldsToUpdate->TMVERTRAGSSTATUSPARTNER = 'aktiv';
    if($request->has('additionalNotesForContract') && !empty($request->input('additionalNotesForContract'))) {
        $registerData->additionalNotesForContract = $request->input('additionalNotesForContract');
        $fieldsToUpdate->TMHINWEISEZUMVERTRAG = $registerData->additionalNotesForContract;
    } else {
        $fieldsToUpdate->TMHINWEISEZUMVERTRAG = '';
    }

    if($request->has('isAlsoEmployer') && $request->input('isAlsoEmployer') == true) {

        $fieldsToUpdate->TMPARTNERINTERESSE = 'Beides';

        $getRegionData = Http::withoutVerifying()->withHeaders([
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
        ])->get(config('services.wordpress.regions.endpoint') . '_fields=acf&region_name=' .
        $interest_personal_data->NCREGION);

        if($getRegionData->failed()) {
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Die Regionsdaten konnten nicht abgerufen werden. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
        }

        $regionData = json_decode($getRegionData);
        if($regionData && count($regionData) > 1) {
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Die Region konnte nicht eindeutig zugeordnet werden. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
        } else {
            $regionData = $regionData[0]->acf;
        }

        $employerContractNumber = generateContractNumber($regionData->contract_number_prefix);

        $guessedSalutation = _guessSalutationFromGW($interest_personal_data->CHRISTIANNAME, $interest_personal_data->NAME, $registerData->gender, '', $interest_personal_data->COUNTRY1);
        $addressterm = $guessedSalutation->addressterm;
        $addressletter = $guessedSalutation->addressletter;

        $employerCompanyID = '';

        if(!$isRegionWithoutValueMaster) {
            $registerEmployerInValueMaster = Http::withHeaders([
                'provider' => 'trolleymaker',
                'password' => 'poiJJ#9q9'
            ])->post('https://valuemaster.brain-behind.com/CU_WebAPI.asmx/CU_Add_Modify_Partner', [
                'CompanyName' => 'MA_CARD-' . $registerData->companyName,
                'CompanyID' => 0,
                'active' => '1',
                'InternalID' => $employerContractNumber,
                'BusinessSector' => array(),
                'PhoneNumer' => '',
                'Street' => $registerData->companyStreet,
                'ZIP' => $registerData->companyZip,
                'City' => $registerData->companyCity,
                'Country' => $countryForVM,
                'Language' => 'de',
                'ReceiveStats' => true,
                'ShowPartner' => true,
                'ReceiveInvoice' => true,
                'ChargeTX' => true,
                'CompanyEmail' => $registerData->companyEmail,
                'Web' => '',
                'BankName' => '',
                'IBAN' => '',
                'BIC' => '',
                'latitude' => 0,
                'longitute' => 0,
                'CompanyNameOnInvoice' => '',
                'CompanyContactPersonOnInvoice' => '',
                'InvoiceStreet' => '',
                'InvoiceZIP' => '',
                'InvoiceCity' => '',
                'InvoiceMail' => '',
                'VATID' => '',
                'logo' => null,
                'Category' => array(),
                'RuleSET' => '',
                'Payment' => 'SEPA_DirectDebit',
                'Admin_User' => [
                    'Sex' => $interest_personal_data->GWGENDER,
                    'PreName' => $interest_personal_data->CHRISTIANNAME,
                    'Name' => $interest_personal_data->NAME,
                    'LoginEmail' => $registerData->employerLoginEmail,
                    'Password' => $registerData->employerPassword,
                    'SendWelcomeMail' => false
                ]
            ]);

            if($registerEmployerInValueMaster->failed() || $registerEmployerInValueMaster == NULL) {
                return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Arbeitgeber-Account konnte nicht erstellt werden. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
            }

            if($registerEmployerInValueMaster && $registerEmployerInValueMaster != NULL) {
                $employerDataFromValueMaster = json_decode($registerEmployerInValueMaster)->d;

                if($employerDataFromValueMaster && $employerDataFromValueMaster != NULL) {
                    if(!property_exists($employerDataFromValueMaster, 'status') || strtolower($employerDataFromValueMaster->status) != 'ok' || !empty($employerDataFromValueMaster->error)) {
                        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Arbeitgeber-Account konnte nicht erstellt werden. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
                    }
                    if(!property_exists($employerDataFromValueMaster, 'CompanyID') || empty($employerDataFromValueMaster->CompanyID)) {
                        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Arbeitgeber-Account konnte nicht erstellt werden. Bitte wenden Sie sich an den Support.', 'unknown_error', 500 );
                    }
                } else {
                    return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Arbeitgeber-Account konnte nicht erstellt werden. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
                }
            } else {
                return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Arbeitgeber-Account konnte nicht erstellt werden. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
            }


            $addTerminalForEmployerResponse = addTerminal(intval($employerDataFromValueMaster->CompanyID), intval($employerDataFromValueMaster->BranchID));
            if(isError($addTerminalForEmployerResponse)) {
                Log::error('Das Terminal konnte nicht angelegt werden.');
                sendErrorNotificationMail('Das Terminal für FirmenID: ' . $employerDataFromValueMaster->CompanyID . ' und BranchID: ' . $employerDataFromValueMaster->BranchID . ' konnte nicht angelegt werden.');
            }

            $employerCompanyID = strval($employerDataFromValueMaster->CompanyID);
        } else {
            $generatePartnerIdResponse = Http::withHeaders([
                'X-API-Key' => config('newRegions.go_backend_api_key'),
            ])->get(config('newRegions.go_backend_url') . '/portals/api/v1/partners/generate-id');

            if($generatePartnerIdResponse->failed() || $generatePartnerIdResponse == NULL) {
                Log::Error('Partner-ID konnte nicht generiert werden: ' . ($generatePartnerIdResponse ? $generatePartnerIdResponse->body() : 'NULL'));
                return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Arbeitgeber-Account konnte nicht erstellt werden. Bitte wenden Sie sich an den Support.', 'partner_id_generation_failed', 500);
            }

            $partnerIdData = json_decode($generatePartnerIdResponse);
            if(!$partnerIdData || !property_exists($partnerIdData, 'partnerId') || empty($partnerIdData->partnerId)) {
                Log::Error('Partner-ID konnte nicht aus der Antwort gelesen werden: ' . $generatePartnerIdResponse->body());
                return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Arbeitgeber-Account konnte nicht erstellt werden. Bitte wenden Sie sich an den Support.', 'partner_id_generation_failed', 500);
            }

            $employerCompanyID = strval($partnerIdData->partnerId);
        }

        $registerData->employerCompanyID = $employerCompanyID;

        $employer_gguid = '';

        $gwEmployerCompanyResponse = Http::withHeaders([
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
            'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
        ])->post(env('GW_API_BASE') . '/type/address', [
            'fields' => [
                'GWSTYPE' => 'Partnerschaft',
                'TMARTDERPARTNERSCHAFT' => 'Partner',
                'TMMODULEPARTNER' => 'MitarbeiterCARD',
                'MAILFIELDSTR4' => $registerData->companyEmail,
                'COMPNAME' => $registerData->companyName,
                'NCREFIRMA' => $registerData->companyName,
                'GWADDITIONALINFO1' => $registerData->companyAddressAdditional,
                'STREET1' => $registerData->companyStreet,
                'NCRESTREET' => $registerData->companyStreet,
                'TOWN1' => $registerData->companyCity,
                'NCREORT' => $registerData->companyCity,
                'ZIP1' => $registerData->companyZip,
                'NCREZIP' => $registerData->companyZip,
                'COUNTRY1' => $registerData->companyCountry,
                'TMRELAND' => $registerData->companyCountry,
                'NCREGION' => $interest_personal_data->NCREGION,
                'NCORTDERANMELDUNG' => $interest_personal_data->NCORTDERANMELDUNG,
                'NCREGISTRIERTSEIT' => $registerData->registeredSince,
                'TMFIRMENINHABER' => $registerData->ceoName,
                'PHONEFIELDSTR9' => $registerData->ceoPhone,
                'TMEINGANGAUFTRAGPARTNER' => $registerData->registeredSince,
                'NCFIRMENID' => $registerData->employerCompanyID,
                'NCINTERNEID' => $employerContractNumber,
                'TMVERTRAGID' => $employerContractNumber,
                'TMHINWEISEZUMVERTRAG' => $fieldsToUpdate->TMHINWEISEZUMVERTRAG,
                'GWISCONTACT' => false,
                'GWISCOMPANY' => true,
                'ISORGANISATION' => true,
                'TMPARTNERAKTIVIERT' => true,
                'TMBETRAGVERTRAGSABSCHLUSS' => intval($fieldsToUpdate->TMBETRAGVERTRAGSABSCHLUSS),
                'TMVERTRAGSBUNDLE' => $fieldsToUpdate->TMVERTRAGSBUNDLE,
                'TMGEMEINDEZUGEHOERIGKEIT' => $company_data->TMGEMEINDEZUGEHOERIGKEIT,
                'TYPSTANDORT' => 'Zentrale',
                'TMPARTNERINTERESSE' => 'Beides',
                'TMVERTRAGSSTATUSPARTNER' => 'aktiv'
            ]
        ]);

        if($gwEmployerCompanyResponse->failed()) {
            Log::error("Fehler bei Registrierung des Arbeitgeber FIRMA bei gwEmployerCompanyResponse: " . $gwEmployerCompanyResponse);
            return returnNewErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
        }

        if($gwEmployerCompanyResponse->header('Location') == NULL || $gwEmployerCompanyResponse->header('Location') == '') {
            Log::error("Fehler bei Registrierung des Interesent FIRMA bei gW, Location Header für GGUID nicht vorhanden: " . $gwEmployerCompanyResponse);
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
        } else {
            $location_splitted = explode("/", $gwEmployerCompanyResponse->header('Location'));
            $employer_gguid = end($location_splitted);
        }

        $gwEmployerUserResponse = Http::withHeaders([
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
            'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
        ])->post(env('GW_API_BASE') . '/type/address', [
            'fields' => [
                'TMADMINUSER' => $registerData->employerLoginEmail,
                'GWGENDER' => $interest_personal_data->GWGENDER,
                'CHRISTIANNAME' => $interest_personal_data->CHRISTIANNAME,
                'NAME' => $interest_personal_data->NAME,
                'ADDRESSTERM' => $addressterm,
                'ADDRESSLETTER' => $addressletter,
                'NCREGISTRIERTSEIT' => $registerData->registeredSince,
                'NCAKTIV' => true,
                'GWISCONTACT' => true,
                'GWISCOMPANY' => false,
                'ISORGANISATION' => false,
                'GWKEEPCONTACTSYNCHRON' => true,
                'CBPHONE1' => 4,
                'CBPHONE2' => 2,
                'CBPHONE3' => 10,
                'CBFAX1' => 5,
                'CBADDRESS' => 0,
                'PRIMARYORGANISATION' => $employer_gguid,
                'TMADMINUSERROLLE' => $interest_personal_data->TMADMINUSERROLLE,
                'TMANSPRECHPARTNERFUER' => 'MitarbeiterCARD,Vertrag,Rechnung,Technik',
                'TMPARTNERPORTALROLLE' => 'Admin'
            ]
        ]);


        if($gwEmployerUserResponse->successful()) {
            Mail::to($registerData->companyEmail)->send(new RegistrationEmployerCustomerMail($registerData));
            Mail::to('mitarbeitercard@trolleymaker.com')->cc(['vertrieb@trolleymaker.com'])->send(new RegistrationEmployerMail($registerData));
        } else {
            //employer person creation failed, so delete the created company
            $gwDeleteFailedEmployerResponse = Http::withHeaders([
                'Content-Type' => 'application/json; charset=utf-8',
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
                'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
            ])->delete(env('GW_API_BASE') . '/type/address/' . $employer_gguid);

            if($gwDeleteFailedEmployerResponse->successful()) {
                Log::error("Fehler bei Registrierung des Interesent bei gW: " . $gwDeleteFailedEmployerResponse);
                return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Arbeitgeber-Account konnte nicht angelegt werden.', 'unknown_error', 500);
            } else {
                Log::error("Fehler bei Registrierung des Partners bei gW: Arbeigeber-Firma wurde erstellt, aber Arbeitgeber-Ansprechpartner konnte nicht erstellt werden. Das daraufhin löschen der Firma ist fehlgeschalgen: " . $gwDeleteFailedEmployerResponse);
                return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Arbeigeber-Account konnte nicht angelegt werden. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
            }
        }
    }


    if(!updateGwAddressData($company_data->GGUID, $fieldsToUpdate) || !updateGwAddressData($interest_personal_data->GGUID, ['NCINTERESSENTPWD' => NULL, 'TMANSPRECHPARTNERFUER' => 'GutscheinCARD,Vertrag,Rechnung,Technik'])) {
        return returnNewErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
    } else {
        $dateNow = new DateTime('now');
        $dateNow->setTimezone(new DateTimeZone('Europe/Berlin'));
        $registerData->registeredSince = $dateNow->format('d.m.Y H:i:s');

        $partner_user_role = getPartnerRolle('Admin');
        DB::table('mycitycards_sessions')
            ->where('id', $request->input('session_id'))
            ->update(['partner_user_role' => $partner_user_role, 'user_role' => UserRoles::PARTNER, 'company_id' => $fieldsToUpdate->NCFIRMENID]);


        Mail::to($registerData->companyEmail)->send(new RegistrationPartnerCustomerMail($registerData));
        Mail::to('partnerverwaltung@trolleymaker.com')->cc(['vertrieb@trolleymaker.com'])->send(new RegistrationPartnerMail($registerData));
        return response()->json( $registerData, 200 );
    }
})->middleware(['AuthenticateWithSession']);


Route::get('/get-bonus', function (Request $request) {

    $responseToSend = new stdClass();
    $getBonusResponse = _handleGetBonus($request);
    if(isError($getBonusResponse)) {
        return returnErrorObject($getBonusResponse);
    }

    $suggestedValues = _getSuggestedValuesForAddress(['TMDAUERBONUSART', 'TMAKTIONSBONUSART']);

    if(isError($suggestedValues)){
        return returnErrorObject($suggestedValues);
    }

    $suggestedValues['permanentBonusTypes'] = $suggestedValues['TMDAUERBONUSART'];
    unset($suggestedValues['TMDAUERBONUSART']);
    $suggestedValues['actionBonusTypes'] = $suggestedValues['TMAKTIONSBONUSART'];
    unset($suggestedValues['TMAKTIONSBONUSART']);

    $responseToSend->bonusData = $getBonusResponse;
    $responseToSend->suggestedValues = $suggestedValues;

    return response()->json( $responseToSend, 200 );
})->middleware(['AuthenticateWithSession', 'AuthenticateIsPartnerAdmin']);

function _handleGetBonus($request){
    $responseToSend = new stdClass();

    $company_data = getGwPersonalDataByGGUID($request->input('company_gguid'));
    if(!property_exists($company_data, 'GGUID')) {
        return createErrorObject('Es ist ein Fehler aufgetreten. Die Firma wurde nicht gefunden. Bitte kontaktieren Sie den Support.', 'company_not_found', 400 );
    }

    if(!property_exists($company_data, 'TMPARTNERDATENVOLLSTAENDIG') || $company_data->TMPARTNERDATENVOLLSTAENDIG !== true) {
        return createErrorObject('Sie müssen zuerst Ihre Partnerdaten (Persönliche Daten) vervollständigen, um einen Bonus einstellen zu können!', 'access_denied', 400 );
    }

    if(!property_exists($company_data, 'TMGEMEINDEZUGEHOERIGKEIT') || $company_data->TMGEMEINDEZUGEHOERIGKEIT === NULL || empty($company_data->TMGEMEINDEZUGEHOERIGKEIT)) {
        return createErrorObject('Ihrem Account wurde keine Gemeindezugehörigkeit zugewiesen. Bitte wenden Sie sich an den Support!', 'no_gemeindezugehoerigkeit', 400 );
    }

    $responseToSend->bonusActive = property_exists($company_data, 'TMBONUSAKTIVIERT') ? $company_data->TMBONUSAKTIVIERT : false;
    $responseToSend->permanentBonus = property_exists($company_data, 'TMDAUERBONUS') ? $company_data->TMDAUERBONUS : false;
    $responseToSend->actionBonus = property_exists($company_data, 'TMAKTIONSBONUS') ? $company_data->TMAKTIONSBONUS : false;

    //Dauerbonus
    $responseToSend->permanentBonusAdditionalInfo = property_exists($company_data, 'TMBONUS') ? $company_data->TMBONUS : NULL;
    $responseToSend->permanentBonusType = property_exists($company_data, 'TMDAUERBONUSART') ? $company_data->TMDAUERBONUSART : NULL;
    $responseToSend->permanentBonusValueInPercent = property_exists($company_data, 'TMDBONUSINPROZENT') ? $company_data->TMDBONUSINPROZENT : NULL;
    $responseToSend->permanentBonusPercentFromMinimum = property_exists($company_data, 'TMDBONUSPROZENTMINDESTUMSATZ') ? $company_data->TMDBONUSPROZENTMINDESTUMSATZ : NULL;
    $responseToSend->permanentBonusValueInEuro = property_exists($company_data, 'TMDBONUSBETRAG') ? $company_data->TMDBONUSBETRAG : NULL;
    $responseToSend->permanentBonusEuroFromMinimum = property_exists($company_data, 'TMDBONUSBETRAGMINDESTUMSATZ') ? $company_data->TMDBONUSBETRAGMINDESTUMSATZ : NULL;

    if(property_exists($company_data, 'TMDBONUSEINKAUFGESAMT') && $company_data->TMDBONUSEINKAUFGESAMT != '') {
        if($company_data->TMDBONUSEINKAUFGESAMT == 'Ja') {
            $responseToSend->permanentBonusForCompletePurchase = 'Ja';
        } else {
            $responseToSend->permanentBonusForCompletePurchase = 'Nein';
            if($company_data->TMDBONUSEINKAUFGESAMT == 'Nein') {
                if(property_exists($company_data, 'TMDAUERBONUSNURAUF') && !empty($company_data->TMDAUERBONUSNURAUF)) {
                    $responseToSend->permanentBonusExceptOrOnly = 'only';
                    $responseToSend->permanentBonusOnlyText = $company_data->TMDAUERBONUSNURAUF;
                    $responseToSend->permanentBonusForCompletePurchaseDetails = $company_data->TMDAUERBONUSNURAUF;
                }
                if(property_exists($company_data, 'TMDAUERBONUSAUSSERAUF') && !empty($company_data->TMDAUERBONUSAUSSERAUF)) {
                    $responseToSend->permanentBonusExceptOrOnly = 'except';
                    $responseToSend->permanentBonusExceptText = $company_data->TMDAUERBONUSAUSSERAUF;
                    $responseToSend->permanentBonusForCompletePurchaseDetails = $company_data->TMDAUERBONUSAUSSERAUF;
                }
            } else {
                $responseToSend->permanentBonusForCompletePurchaseDetails = $company_data->TMDBONUSEINKAUFGESAMT;
            }
        }
    }

    if(property_exists($company_data, 'TMDBONUSZEITSTEUERUNG') && $company_data->TMDBONUSZEITSTEUERUNG != '') {
        if($company_data->TMDBONUSZEITSTEUERUNG == 'Nein') {
            $responseToSend->permanentBonusTiming = 'Nein';
        } else {
            $responseToSend->permanentBonusTiming = 'Ja';
            $responseToSend->permanentBonusTimingDetails = $company_data->TMDBONUSZEITSTEUERUNG;
        }
    }


    //Aktionsbonus
    $responseToSend->actionBonusAdditionalInfo = property_exists($company_data, 'TMABONUSINFOS') ? $company_data->TMABONUSINFOS : NULL;

    if(property_exists($company_data, 'TMABONUSSTARTDATUM') && $company_data->TMABONUSSTARTDATUM != '' && strlen($company_data->TMABONUSSTARTDATUM) > 0) {
        $actionBonusStartDate = new DateTime($company_data->TMABONUSSTARTDATUM, new DateTimeZone('Europe/Berlin'));
        $responseToSend->actionBonusStartDate = $actionBonusStartDate->format('Y-m-d');
    } else {
        $responseToSend->actionBonusStartDate = NULL;
    }

    if(property_exists($company_data, 'TMABONUSENDDATUM') && $company_data->TMABONUSENDDATUM != '' && strlen($company_data->TMABONUSENDDATUM) > 0) {
        $actionBonusEndDate = new DateTime($company_data->TMABONUSENDDATUM, new DateTimeZone('Europe/Berlin'));
        $responseToSend->actionBonusEndDate = $actionBonusEndDate->format('Y-m-d');
    } else {
        $responseToSend->actionBonusEndDate = NULL;
    }

    $responseToSend->actionBonusType = property_exists($company_data, 'TMAKTIONSBONUSART') ? $company_data->TMAKTIONSBONUSART : NULL;

    $responseToSend->actionBonusValueInPercent = property_exists($company_data, 'TMABONUSINPROZENT') ? $company_data->TMABONUSINPROZENT : NULL;
    $responseToSend->actionBonusPercentFromMinimum = property_exists($company_data, 'TMABONUSPROZENTMINDESTUMSATZ') ? $company_data->TMABONUSPROZENTMINDESTUMSATZ : NULL;

    $responseToSend->actionBonusValueInEuro = property_exists($company_data, 'TMABONUSBETRAG') ? $company_data->TMABONUSBETRAG : NULL;
    $responseToSend->actionBonusEuroFromMinimum = property_exists($company_data, 'TMABONUSBETRAGMINDESTUMSATZ') ? $company_data->TMABONUSBETRAGMINDESTUMSATZ : NULL;

    if(property_exists($company_data, 'TMABONUSEINKAUFGESAMT') && $company_data->TMABONUSEINKAUFGESAMT != '') {
        if($company_data->TMABONUSEINKAUFGESAMT == 'Ja') {
            $responseToSend->actionBonusForCompletePurchase = 'Ja';
        } else {
            $responseToSend->actionBonusForCompletePurchase = 'Nein';
            if($company_data->TMABONUSEINKAUFGESAMT == 'Nein') {
                if(property_exists($company_data, 'TMAKTIONSBONUSNURAUF') && !empty($company_data->TMAKTIONSBONUSNURAUF)) {
                    $responseToSend->actionBonusExceptOrOnly = 'only';
                    $responseToSend->actionBonusOnlyText = $company_data->TMAKTIONSBONUSNURAUF;
                    $responseToSend->actionBonusForCompletePurchaseDetails = $company_data->TMAKTIONSBONUSNURAUF;
                }
                if(property_exists($company_data, 'TMAKTIONSBONUSAUSSERAUF') && !empty($company_data->TMAKTIONSBONUSAUSSERAUF)) {
                    $responseToSend->actionBonusExceptOrOnly = 'except';
                    $responseToSend->actionBonusExceptText = $company_data->TMAKTIONSBONUSAUSSERAUF;
                    $responseToSend->actionBonusForCompletePurchaseDetails = $company_data->TMAKTIONSBONUSAUSSERAUF;
                }
            } else {
                $responseToSend->actionBonusForCompletePurchaseDetails = $company_data->TMDBONUSEINKAUFGESAMT;
            }
        }
    }

    if(property_exists($company_data, 'TMABONUSZEITSTEUERUNG') && $company_data->TMABONUSZEITSTEUERUNG != '') {
        if($company_data->TMABONUSZEITSTEUERUNG == 'Nein') {
            $responseToSend->actionBonusTiming = 'Nein';
        } else {
            $responseToSend->actionBonusTiming = 'Ja';
            $responseToSend->actionBonusTimingDetails = $company_data->TMABONUSZEITSTEUERUNG;
        }
    }

    $responseToSend->community = getShortCommunityString($company_data->TMGEMEINDEZUGEHOERIGKEIT);

    return $responseToSend;
}



Route::post('/set-bonus', function (Request $request) {

    $handle_set_bonus = handleSetBonus($request);

    if(isError($handle_set_bonus)) {
        return returnErrorObject($handle_set_bonus);
    }

    return response()->json( $handle_set_bonus, 200 );

})->middleware(['AuthenticateWithSession', 'AuthenticateIsPartnerAdmin']);


function handleSetBonus($request) {

    if(!_isPartnerAdmin($request)) {
        return createErrorObject('Sie haben nicht die benötigte Berechtigung, um einen Bonus einzustellen. Bitte kontaktieren Sie den Support.', 'access_denied', 400);
    }

    $fieldsToUpdate = new stdClass();
    $bonusMailData = new stdClass();

    $dateNow = new DateTime('now');
    $dateNow->setTimezone(new DateTimeZone('Europe/Berlin'));
    $bonusMailData->currentTimestamp = $dateNow->format('d.m.Y H:i:s');


    if(!$request->has('bonusActive')) {
        Log::error("Fehler bei Set Bonus: Es wurde bonusActive nicht mitgeschickt");
        return createErrorObject('Es wurde nicht angegeben ob bonusActive!', 'no_bonusActive', 400);
    } else {
        if(!($request->input('bonusActive') === true || $request->input('bonusActive') === false)) {
            Log::error("Fehler bei Set Bonus: Das Feld bonusActive ist weder true noch false");
            return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
        }
    }


    $company_data = getGwPersonalDataByGGUID($request->input('company_gguid'));
    if(!property_exists($company_data, 'GGUID')) {
        return createErrorObject('Es ist ein Fehler aufgetreten. Die Firma wurde nicht gefunden. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
    }

    $personal_data = getGwPersonalDataByGGUID($request->input('contact_person_gguid'));
    if(!property_exists($personal_data, 'GGUID')) {
        return createErrorObject('Es ist ein Fehler aufgetreten. Ihr Account wurde nicht gefunden. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
    }

    if(!property_exists($company_data, 'TMPARTNERDATENVOLLSTAENDIG') || $company_data->TMPARTNERDATENVOLLSTAENDIG !== true) {
        return createErrorObject('Sie müssen zuerst Ihre Partnerdaten vervollständigen/bearbeiten, bevor Sie einen Bonus einstellen können.', 'not_completed_personal_data', 400);
    }


    $bonusMailData->contactPersonEmail = $request->input('email');
    $bonusMailData->cardName = $company_data->NCORTDERANMELDUNG;
    $bonusMailData->companyName = $company_data->COMPNAME;
    $bonusMailData->contactPersonFirstName = $personal_data->CHRISTIANNAME;
    $bonusMailData->contactPersonLastName = $personal_data->NAME;

    if($request->input('bonusActive') === false) {
        $fieldsToUpdate->TMBONUSAKTIVIERT = false;
        if(!updateGwAddressData($request->input('company_gguid'), $fieldsToUpdate)) {
            return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
        }
    } else {

        $fieldsToUpdate->TMBONUSAKTIVIERT = true;

        $fieldsToUpdate->TMDAUERBONUS = $request->has('permanentBonus') && $request->input('permanentBonus') === true? true: false;
        $fieldsToUpdate->TMAKTIONSBONUS = $request->has('actionBonus') && $request->input('actionBonus') === true? true: false;


        //Dauerbonus
        $fieldsToUpdate->TMBONUS = $request->has('permanentBonusAdditionalInfo') ? $request->input('permanentBonusAdditionalInfo') : '';
        $fieldsToUpdate->TMDAUERBONUSART = $request->input('permanentBonusType');
        $fieldsToUpdate->TMDBONUSINPROZENT = $request->input('permanentBonusValueInPercent');
        $fieldsToUpdate->TMDBONUSPROZENTMINDESTUMSATZ = $request->input('permanentBonusPercentFromMinimum');
        $fieldsToUpdate->TMDBONUSBETRAG = $request->input('permanentBonusValueInEuro');
        $fieldsToUpdate->TMDBONUSBETRAGMINDESTUMSATZ = $request->input('permanentBonusEuroFromMinimum');

        if($request->input('permanentBonusForCompletePurchase') == 'Ja') {
            $fieldsToUpdate->TMDBONUSEINKAUFGESAMT = 'Ja';
        } else {
            if($request->has('permanentBonusExceptOrOnly') && !empty($request->input('permanentBonusExceptOrOnly'))) {
                $fieldsToUpdate->TMDBONUSEINKAUFGESAMT = 'Nein';
                if($request->input('permanentBonusExceptOrOnly') != 'except' && $request->input('permanentBonusExceptOrOnly') != 'only') {
                    return createErrorObject('Ungültiger Wert für Außer auf... / Nur auf... .', 'invalid_permanentBonusExceptOrOnly', 400);
                }

                if($request->input('permanentBonusExceptOrOnly') == 'except') {
                    if(!$request->has('permanentBonusExceptText') || empty($request->input('permanentBonusExceptText'))) {
                        return createErrorObject('Es wurden keine weiteren Details für Bonus "außer auf..." angegeben', 'invalid_permanentBonusExceptText', 400);
                    }
                    $fieldsToUpdate->TMDAUERBONUSAUSSERAUF = $request->input('permanentBonusExceptText');
                    $fieldsToUpdate->TMDAUERBONUSNURAUF = '';
                } else if($request->input('permanentBonusExceptOrOnly') == 'only') {
                    if(!$request->has('permanentBonusOnlyText') || empty($request->input('permanentBonusOnlyText'))) {
                        return createErrorObject('Es wurden keine weiteren Details für Bonus "nur auf..." angegeben', 'invalid_permanentBonusOnlyText', 400);
                    }
                    $fieldsToUpdate->TMDAUERBONUSNURAUF = $request->input('permanentBonusOnlyText');
                    $fieldsToUpdate->TMDAUERBONUSAUSSERAUF = '';
                }
            } else {
                $fieldsToUpdate->TMDBONUSEINKAUFGESAMT = $request->input('permanentBonusForCompletePurchaseDetails');
            }
        }

        if($request->input('permanentBonusTiming') == 'Nein') {
            $fieldsToUpdate->TMDBONUSZEITSTEUERUNG = 'Nein';
        } else {
            $fieldsToUpdate->TMDBONUSZEITSTEUERUNG = $request->input('permanentBonusTimingDetails');
        }



        //Aktionsbonus
        if($request->has('actionBonusStartDate') && $request->input('actionBonusStartDate') != '') {
            if(validateDate($request->input('actionBonusStartDate') . ' 00:00:00', 'Y-m-d H:i:s')) {
                $actionBonusStartDate = new DateTime($request->input('actionBonusStartDate') . ' 00:00:00', new DateTimeZone('Europe/Berlin'));
                $fieldsToUpdate->TMABONUSSTARTDATUM = $actionBonusStartDate->format("Y-m-d\TH:i:s\Z");
            } else {
                return createErrorObject('Das Startdatum ist ungültig.', 'invalid_actionBonusStartDate', 400 );
            }
        } else {
            $fieldsToUpdate->TMABONUSSTARTDATUM = NULL;
        }

        if($request->has('actionBonusEndDate') && $request->input('actionBonusEndDate') != '') {
            if(validateDate($request->input('actionBonusEndDate') . ' 00:00:00', 'Y-m-d H:i:s')) {
                $actionBonusEndDate = new DateTime($request->input('actionBonusEndDate') . ' 00:00:00', new DateTimeZone('Europe/Berlin'));
                $fieldsToUpdate->TMABONUSENDDATUM = $actionBonusEndDate->format("Y-m-d\TH:i:s\Z");
            } else {
                return createErrorObject('Das Enddatum ist ungültig.', 'invalid_actionBonusEndDate', 400 );
            }
        } else {
            $fieldsToUpdate->TMABONUSENDDATUM = NULL;
        }

        $fieldsToUpdate->TMABONUSINFOS = $request->has('actionBonusAdditionalInfo') ? $request->input('actionBonusAdditionalInfo') : '';
        $fieldsToUpdate->TMAKTIONSBONUSART = $request->input('actionBonusType');
        $fieldsToUpdate->TMABONUSINPROZENT = $request->input('actionBonusValueInPercent');
        $fieldsToUpdate->TMABONUSPROZENTMINDESTUMSATZ = $request->input('actionBonusPercentFromMinimum');
        $fieldsToUpdate->TMABONUSBETRAG = $request->input('actionBonusValueInEuro');
        $fieldsToUpdate->TMABONUSBETRAGMINDESTUMSATZ = $request->input('actionBonusEuroFromMinimum');

        if($request->input('actionBonusForCompletePurchase') == 'Ja') {
            $fieldsToUpdate->TMABONUSEINKAUFGESAMT = 'Ja';
        } else {
            if($request->has('actionBonusExceptOrOnly') && !empty($request->input('actionBonusExceptOrOnly'))) {
                $fieldsToUpdate->TMABONUSEINKAUFGESAMT = 'Nein';
                if($request->input('actionBonusExceptOrOnly') != 'except' && $request->input('actionBonusExceptOrOnly') != 'only') {
                    return createErrorObject('Ungültiger Wert für Außer auf... / Nur auf... .', 'invalid_actionBonusExceptOrOnly', 400);
                }

                if($request->input('actionBonusExceptOrOnly') == 'except') {
                    if(!$request->has('actionBonusExceptText') || empty($request->input('actionBonusExceptText'))) {
                        return createErrorObject('Es wurden keine weiteren Details für Bonus "außer auf..." angegeben', 'invalid_actionBonusExceptText', 400);
                    }
                    $fieldsToUpdate->TMAKTIONSBONUSAUSSERAUF = $request->input('actionBonusExceptText');
                    $fieldsToUpdate->TMAKTIONSBONUSNURAUF = '';
                } else if($request->input('actionBonusExceptOrOnly') == 'only') {
                    if(!$request->has('actionBonusOnlyText') || empty($request->input('actionBonusOnlyText'))) {
                        return createErrorObject('Es wurden keine weiteren Details für Bonus "nur auf..." angegeben', 'invalid_actionBonusOnlyText', 400);
                    }
                    $fieldsToUpdate->TMAKTIONSBONUSNURAUF = $request->input('actionBonusOnlyText');
                    $fieldsToUpdate->TMAKTIONSBONUSAUSSERAUF = '';
                }
            } else {
                $fieldsToUpdate->TMABONUSEINKAUFGESAMT = $request->input('actionBonusForCompletePurchaseDetails');
            }
        }

        if($request->input('actionBonusTiming') == 'Nein') {
            $fieldsToUpdate->TMABONUSZEITSTEUERUNG = 'Nein';
        } else {
            $fieldsToUpdate->TMABONUSZEITSTEUERUNG = $request->input('actionBonusTimingDetails');
        }

        if(!updateGwAddressData($request->input('company_gguid'), $fieldsToUpdate)) {
            return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
        }
    }

    if(!property_exists($company_data, 'TMISTTESTDATENSATZ') || $company_data->TMISTTESTDATENSATZ != true) {
        Mail::to($bonusMailData->contactPersonEmail)->send(new SetBonusCustomerMail($bonusMailData));
        Mail::to('technik@trolleymaker.com')->cc(['vertrieb@trolleymaker.com', 'partnerverwaltung@trolleymaker.com'])->send(new SetBonusMail($bonusMailData));
    }

    return $request->input();
}


Route::get('/get-booking', function (Request $request) {

    $returnFromHandle = _handleGetBooking($request);
    if(isError($returnFromHandle)) {
        return returnErrorObject($returnFromHandle);
    }

    return response()->json( $returnFromHandle, 200 );
})->middleware(['AuthenticateWithSession', 'AuthenticateIsPartnerAdminOrUser']);

function _handleGetBooking($request){

    $company_data = getGwPersonalDataByGGUID($request->input('company_gguid'));
    if(!property_exists($company_data, 'GGUID')) {
        return createErrorObject('Es ist ein Fehler aufgetreten. Die Firma wurde nicht gefunden. Bitte kontaktieren Sie den Support.', 'company_not_found', 400 );
    }

    if(!property_exists($company_data, 'TYPSTANDORT')) {
        return createErrorObject('Bei Ihrer Firma konnte nicht ermittelt werden, ob es sich um eine Zentrale oder Filiale handelt. Bitte wenden Sie sich an den Support.', 'headquarter_or_branch_unclear', 401 );
    }

    if(strtolower($company_data->TYPSTANDORT) != 'zentrale') {
        //get zentrale
        $gwGetHeadquarter = Http::withHeaders([
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
            'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
        ])->get(env('GW_API_BASE') . '/type/address/full?linked-to=' . $company_data->GGUID . '&linked-to-type=ADDRESS&linked-to-attributes=FILIALEZENTRALE&order-by=INSERTTIMESTAMP');

        if($gwGetHeadquarter->failed()) {
            if($gwGetHeadquarter->status() == 503) {
                return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'server_unavailable', 503 );
            }
            Log::error("Fehler beim Abrufen von gwGetHeadquarter: " . print_r($gwGetHeadquarter, true));
            return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 400 );
        }

        $gwLinkedHeadquarter = json_decode($gwGetHeadquarter);

        if(count($gwLinkedHeadquarter) < 1) {
            Log::error("Fehler beim Abrufen von Zentrale " . $gwLinkedHeadquarter);
            return createErrorObject('Die Zentrale der Firma konnte nicht ermittelt werden. Bitte wenden Sie sich an den Support.', 'headquarter_unknown', 400 );
        }

        $headquarter_data = getGwPersonalDataByGGUID($gwLinkedHeadquarter[0]->fields->GGUID);

        if(!property_exists($headquarter_data, 'GGUID')) {
            Log::error("Fehler beim Abrufen von Zentrale " . $gwLinkedHeadquarter);
            return createErrorObject('Es konnte die Zentrale Firma nicht ermittelt werden. Bitte wenden Sie sich an den Support.', 'headquarter_unknown', 400 );
        }
    } else {
        $headquarter_data = $company_data;
    }


    if(!property_exists($headquarter_data, 'TMPARTNERDATENVOLLSTAENDIG') || $headquarter_data->TMPARTNERDATENVOLLSTAENDIG !== true) {
        return createErrorObject('Sie müssen zuerst Ihre Partnerdaten (Persönliche Daten) vervollständigen, um das Buchungsportal nutzen zu können!', 'complete_data_first', 400 );
    }

    $returnObject = new stdClass();
    $returnObject->bonusActive = property_exists($headquarter_data, 'TMBONUSAKTIVIERT') ? $headquarter_data->TMBONUSAKTIVIERT : false;

    return $returnObject;
}


Route::post('/partner-check-balance', function (Request $request) {

    if(!$request->has('inputCardID') || $request->input('inputCardID') == '' || strlen($request->input('inputCardID')) == 0) {
        return response()->json( ['errorMessage' => 'Es wurde keine Kartennummer angegeben.'], 400 );
    }

    $inputCardID = trim($request->input('inputCardID'));

    $balance = getBalanceAmountForCardID($inputCardID);



    $cardCheck = _checkIfBookingIsAllowedForCard($inputCardID, $request->input('region_name'), $request->input('card_name'));
    $balance['isCardRegistered'] = false;
    if(is_object($cardCheck)) {
        if(property_exists($cardCheck, 'remainingAmountCentToAddVoucherThisMonth') && $cardCheck->remainingAmountCentToAddVoucherThisMonth !== null) {
            $balance['remainingAmountCentToAddVoucherThisMonth'] = $cardCheck->remainingAmountCentToAddVoucherThisMonth;
        }
        if(property_exists($cardCheck, 'remainingAmountToAddVoucherThisMonthFormattedDE') && $cardCheck->remainingAmountToAddVoucherThisMonthFormattedDE !== null) {
            $balance['remainingAmountToAddVoucherThisMonthFormattedDE'] = $cardCheck->remainingAmountToAddVoucherThisMonthFormattedDE;
        }
        if(property_exists($cardCheck, 'remainingAmountToAddVoucherThisMonthFormattedEN') && $cardCheck->remainingAmountToAddVoucherThisMonthFormattedEN !== null) {
            $balance['remainingAmountToAddVoucherThisMonthFormattedEN'] = $cardCheck->remainingAmountToAddVoucherThisMonthFormattedEN;
        }
        if(property_exists($cardCheck, 'isTestcard') && $cardCheck->isTestcard !== null) {
            $balance['isTestcard'] = $cardCheck->isTestcard;
        }
        if(property_exists($cardCheck, 'errorMessage')) {
            unset($balance['balanceFormattedDE']);
            unset($balance['balanceFormattedEN']);
            unset($balance['balanceCent']);
            unset($balance['isCardRegistered']);
            unset($balance['isTestcard']);
            $balance['errorMessage'] = $cardCheck->errorMessage;
            return response()->json( $balance, 500 );
        } else {
            if(!$cardCheck->isBookingAllowed) {
                $balance['errorMessage'] = 'Die Kartennummer ist nicht gültig';
                return response()->json( $balance, 500 );
            } else {
                $balance['isCardRegistered'] = $cardCheck->isCardRegistered;
            }
        }
    } else {
        unset($balance['balanceFormattedDE']);
        unset($balance['balanceFormattedEN']);
        unset($balance['balanceCent']);
        unset($balance['isCardRegistered']);
        unset($balance['isTestcard']);
        $balance['errorMessage'] = 'Es ist ein unbekannter Fehler aufgetreten. Bitte kontaktieren Sie den Support.';
    }

    if(array_key_exists('errorMessage', $balance) && !empty($balance['errorMessage'])) {
        return response()->json( $balance, 500 );
    } else {
        return response()->json( $balance, 200 );
    }

})->middleware(['AuthenticateWithSession', 'AuthenticateIsPartner']);


Route::get('/get-correction-booking', function (Request $request) {

    $responseToSend = new stdClass();

    $company_data = getGwPersonalDataByGGUID($request->input('company_gguid'));
    if(!property_exists($company_data, 'GGUID')) {
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Die Firma wurde nicht gefunden. Bitte kontaktieren Sie den Support.', 'company_not_found', 500);
    }

    if(!property_exists($company_data, 'TMPARTNERDATENVOLLSTAENDIG') || $company_data->TMPARTNERDATENVOLLSTAENDIG !== true) {
        return returnNewErrorObject('Sie müssen zuerst Ihre Partnerdaten (Persönliche Daten) vervollständigen, bevor Sie Buchungen vornehmen können und Korrekturbuchungen einreichen können!', 'personal_data_not_completed', 400);
    }

    if(!property_exists($company_data, 'TMVERTRAGID') || $company_data->TMVERTRAGID == '') {
        return returnNewErrorObject('Es wurde keine Vertragsnummer für Ihren Account gefunden! Bitte kontaktieren Sie den Support.', 'no_contract_id', 400);
    }

    $responseToSend->contractID = $company_data->TMVERTRAGID;
    $responseToSend->partnerName = property_exists($company_data, 'COMPNAME') ? $company_data->COMPNAME : '';

    $personal_data = getGwPersonalDataByGGUID($request->input('contact_person_gguid'));
    if(!property_exists($personal_data, 'GGUID')) {
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Account wurde nicht gefunden. Bitte kontaktieren Sie den Support.', 'account_not_found', 400);
    }

    $responseToSend->contactName = $personal_data->CHRISTIANNAME . ' ' . $personal_data->NAME;
    $responseToSend->contactEmail = property_exists($personal_data, 'MAILFIELDSTR4') ? $personal_data->MAILFIELDSTR4 : '';

    return response()->json( $responseToSend, 200 );

})->middleware(['AuthenticateWithSession', 'AuthenticateIsPartnerOrEmployer']);



Route::post('/correction-booking', function (Request $request) {
    $correctionBooking = _handleCorrectionBooking($request);

    if(isError($correctionBooking)) {
        return returnErrorObject($correctionBooking);
    }

    return response()->json( new stdClass(), 200 );
})->middleware(['AuthenticateWithSession', 'AuthenticateIsPartner']);

function _handleCorrectionBooking($request){
    if(!$request->has('cardID') || $request->input('cardID') === null || $request->input('cardID') == '') {
        return createErrorObject('Es wurde keine Kartennummer angegeben.', 'no_cardID', 400 );
    }

    if(!str_starts_with($request->input('cardID'), '1761')) {
        return createErrorObject('Die Kartennummer ist ungültig.', 'invalid_cardID', 400 );
    }

    if(!$request->has('bookingTimestamp') || $request->input('bookingTimestamp') === null || $request->input('bookingTimestamp') == '') {
        return createErrorObject('Es wurde kein Buchungszeitpunkt angegeben.', 'no_bookingTime', 400 );
    }

    if(!$request->has('contractID') || $request->input('contractID') === null || $request->input('contractID') == '') {
        return createErrorObject('Es wurde keine Vertragsnummer angegeben.', 'no_contractID', 400 );
    }

    if(!$request->has('partnerName') || $request->input('partnerName') === null || $request->input('partnerName') == '') {
        return createErrorObject('Es wurde kein Partnername angegeben.', 'no_partnername', 400 );
    }

    if(!$request->has('contactEmail') || $request->input('contactEmail') === null || $request->input('contactEmail') == '') {
        return createErrorObject('Es wurde keine Kontakt E-Mail-Adresse angegeben.', 'no_contact_email', 400 );
    }

    if(!$request->has('contactName') || $request->input('contactName') === null || $request->input('contactName') == '') {
        return createErrorObject('Es wurde kein Kontakt Name angegeben.', 'no_contact_name', 400 );
    }

    if($request->input('isAddVoucher') === false && $request->input('isRedeemVoucher') === false && $request->input('isAddBonus') === false) {
        return createErrorObject('Es wurde kein "Welche Buchung soll korrigiert werden?" angegeben.', 'missing_which_booking_should_be_corrected', 400 );
    }

    if($request->has('isAddVoucher') == false) {
        $request->merge(['isAddVoucher' => false]);
    }

    if($request->has('isRedeemVoucher') == false) {
        $request->merge(['isRedeemVoucher' => false]);
    }

    if($request->has('isAddBonus') == false) {
        $request->merge(['isAddBonus' => false]);
    }

    if($request->has('addVoucherAmount') == false) {
        $request->merge(['addVoucherAmount' => '']);
    }

    if($request->has('redeemVoucherAmount') == false) {
        $request->merge(['redeemVoucherAmount' => '']);
    }

    if($request->has('addBonusAmount') == false) {
        $request->merge(['addBonusAmount' => '']);
    }

    if($request->has('shouldAddVoucher') == false) {
        $request->merge(['shouldAddVoucher' => false]);
    }

    if($request->has('shouldRedeemVoucher') == false) {
        $request->merge(['shouldRedeemVoucher' => false]);
    }

    if($request->has('shouldAddBonus') == false) {
        $request->merge(['shouldAddBonus' => false]);
    }

    if($request->has('shouldAddVoucherAmount') == false) {
        $request->merge(['shouldAddVoucherAmount' => '']);
    }

    if($request->has('shouldRedeemVoucherAmount') == false) {
        $request->merge(['shouldRedeemVoucherAmount' => '']);
    }

    if($request->has('shouldAddBonusAmount') == false) {
        $request->merge(['shouldAddBonusAmount' => '']);
    }

    if($request->has('message') == false || $request->input('message') == NULL) {
        $request->merge(['message' => '']);
    }

    if($request->input('isAddVoucher') === true && $request->input('addVoucherAmount') == '') {
        return createErrorObject('Es wurde kein Guthaben aufgebucht Betrag angegeben.', 'no_addVoucher_amount', 400 );
    }

    if($request->input('isRedeemVoucher') === true && $request->input('redeemVoucherAmount') == '') {
        return createErrorObject('Es wurde kein Guthaben eingelöst Betrag angegeben.', 'no_redeemVoucher_amount', 400 );
    }

    if($request->input('isAddBonus') === true && $request->input('addBonusAmount') == '') {
        return createErrorObject('Es wurde kein Einkauf gebucht (Bonus) Betrag angegeben.', 'no_addBonus_amount', 400 );
    }

    if($request->input('shouldAddVoucher') === false && $request->input('shouldRedeemVoucher') === false && $request->input('shouldAddBonus') === false) {
        return createErrorObject('"Welche Buchung wäre korrekt gewesen?" angegeben.', 'missing_information_about_correct_booking', 400 );
    }

    if($request->input('shouldAddVoucher') === true && $request->input('shouldAddVoucherAmount') == '') {
        return createErrorObject('Es wurde kein Guthaben aufgebucht Betrag angegeben.', 'no_shouldAddVoucher_amount', 400 );
    }

    if($request->input('shouldRedeemVoucher') === true && $request->input('shouldRedeemVoucherAmount') == '') {
        return createErrorObject('Es wurde kein Guthaben eingelöst Betrag angegeben.', 'no_shouldRedeemVoucher_amount', 400 );
    }

    if($request->input('shouldAddBonus') === true && $request->input('shouldAddBonusAmount') == '') {
        return createErrorObject('Es wurde kein Einkauf gebucht (Bonus) Betrag angegeben.', 'no_shouldAddBonus_amount', 400 );
    }

    $correctionBookingData = (object) $request->input();

    $dateNow = new DateTime('now');
    $dateNow->setTimezone(new DateTimeZone('Europe/Berlin'));
    $correctionBookingData->currentTimestamp = $dateNow->format('d.m.Y H:i:s');

    if(!property_exists($correctionBookingData, 'phone')) {
        $correctionBookingData->contactPhone = '';
    }

    if(validateDate($correctionBookingData->bookingTimestamp, 'Y-m-d\TH:i:s')) {
        $bookingTimestamp = new DateTime($correctionBookingData->bookingTimestamp, new DateTimeZone('Europe/Berlin'));
        $correctionBookingData->bookingTimestamp = $bookingTimestamp->format('d.m.Y H:i:s');
    } else {
        return createErrorObject('Der Buchungszeitpunkt ist ungültig.', 'invalid_booking_time', 400 );
    }

    Mail::to($correctionBookingData->contactEmail)->send(new CorrectionBookingCustomerMail($correctionBookingData));
    Mail::to(env('MAIL_MASTER_TO_ADDRESS'))->send(new CorrectionBookingMail($correctionBookingData));
    return new stdClass();
}


Route::post('/check-ec-terminal', function (Request $request) {

    if(!$request->has('ecManufacturer') || $request->input('ecManufacturer') == '') {
        return response()->json( [ 'errorMessage' => 'Es wurde kein Hersteller angegeben.' ], 400 );
    }

    if(!$request->has('ecType') || $request->input('ecType') == '') {
        return response()->json( [ 'errorMessage' => 'Es wurde kein Typ EC-Gerät angegeben.' ], 400 );
    }

    if(!$request->has('ecTerminalID') || $request->input('ecTerminalID') == '') {
        return response()->json( [ 'errorMessage' => 'Es wurde keine Terminal-ID (TID) angegeben.' ], 400 );
    }

    if(!$request->has('ecSerialNumber') || $request->input('ecSerialNumber') == '') {
        return response()->json( [ 'errorMessage' => 'Es wurde keine Seriennummer angegeben.' ], 400 );
    }

    if($request->has('ecCashpointIntegration') && $request->input('ecCashpointIntegration') === true) {
        if(!$request->has('ecCashpointIntegrationManufacturer') || $request->input('ecCashpointIntegrationManufacturer') == '') {
            return response()->json( [ 'errorMessage' => 'Es wurde kein Anbieter Handelskassensoftware angegeben.' ], 400 );
        }
    }

    $company_data = getGwPersonalDataByGGUID($request->input('company_gguid'));
    if(!property_exists($company_data, 'GGUID')) {
        return response()->json( [ 'errorMessage' => 'Es ist ein Fehler aufgetreten. Die Firma wurde nicht gefunden. Bitte kontaktieren Sie den Support.' ], 400 );
    }

    $personal_data = getGwPersonalDataByGGUID($request->input('contact_person_gguid'));
    if(!property_exists($personal_data, 'GGUID')) {
        return response()->json( [ 'errorMessage' => 'Es ist ein Fehler aufgetreten. Der Ansprechpartner wurde nicht gefunden. Bitte kontaktieren Sie den Support.' ], 400 );
    }

    if(!property_exists($company_data, 'TMVERTRAGID') || empty($company_data->TMVERTRAGID)) {
        return response()->json( [ 'errorMessage' => 'Ihre Vertragsnummer wurde nicht gefunden. Bitte wenden Sie sich an den Support!' ], 500 );
    }

    $handlingAtPosData = $request->except(['email', 'company_gguid', 'contact_person_gguid', 'partner_user_role', 'session_id']);

    $handlingAtPosData = json_decode(json_encode($handlingAtPosData), FALSE);
    $handlingAtPosData->firstName = $personal_data->CHRISTIANNAME;
    $handlingAtPosData->lastName = $personal_data->NAME;
    $handlingAtPosData->email = $personal_data->TMADMINUSER;
    $handlingAtPosData->companyName = $company_data->COMPNAME;
    $handlingAtPosData->companyEmail = $company_data->MAILFIELDSTR5;
    $handlingAtPosData->companyStreet = $company_data->STREET1;
    $handlingAtPosData->companyCity = $company_data->TOWN1;
    $handlingAtPosData->companyZip = $company_data->ZIP1;
    $handlingAtPosData->cardName = $company_data->NCORTDERANMELDUNG;
    $handlingAtPosData->contractNumber = $company_data->TMVERTRAGID;

    $dateNow = new DateTime('now');
    $dateNow->setTimezone(new DateTimeZone('Europe/Berlin'));
    $handlingAtPosData->currentTimestamp = $dateNow->format('d.m.Y H:i:s');

    Mail::to($handlingAtPosData->email)->send(new CheckEcTerminalCustomerMail($handlingAtPosData));
    Mail::to(env('MAIL_MASTER_TO_ADDRESS'))->send(new CheckEcTerminalMail($handlingAtPosData));
    return response()->json( '{}', 200 );

})->middleware(['AuthenticateWithSession']);



Route::post('/employer-registration', function (Request $request) {

    foreach(array('ceoName', 'ceoPhone', 'companyName', 'companyStreet', 'companyZip', 'companyCity', 'companyCountry', 'companyEmail', 'companyEmailRepeated', 'contactPersonEmail') as $input) {
        if(!$request->has($input) || $request->input($input) == NULL || $request->input($input) == '') {
            return returnNewErrorObject('Es wurden nicht alle erforderlichen Felder ausgefüllt!', 'missing_fields', 400);
        }
    }

    $registerData = new stdClass();
    $registerData->TMADMINUSER = trim($request->input('contactPersonEmail'));

    $registerData->companyCountry = $request->input('companyCountry');


    if($request->input('companyEmail') != $request->input('companyEmailRepeated')) {
        return returnNewErrorObject('Die beiden E-Mail Adressen stimmen nicht überein!' , 'invalid_email_repeated', 400);
    } else {
        $registerData->companyEmail = trim($request->input('companyEmail'));
        $registerData->email = trim($request->input('companyEmail'));
    }



    $interest_personal_data = getGwInterestAndPartnerPersonalData('GGUID, PRIMARYORGANISATION, NCREGION, NCINTERNEID, GWGENDER, CHRISTIANNAME, NAME, TMADMINUSER, NCINTERESSENTPWD, NCORTDERANMELDUNG, TMADMINUSERROLLE', $registerData->TMADMINUSER, true, false);

    if(isError($interest_personal_data)) {
        return returnErrorObject($interest_personal_data);
    }

    $company_data = getGwPersonalDataByGGUID($interest_personal_data->PRIMARYORGANISATION);

    if(!property_exists($company_data, 'GGUID')) {
        return returnNewErrorObject('Es wurde keine Firma zu dem Ansprechpartner gefunden. Bitte wenden Sie sich an den Support.', 'company_not_found', 400);
    }

    $isRegionWithoutValueMaster = in_array($interest_personal_data->NCREGION, config('newRegions.regions_without_valuemaster'));

    if(!$isRegionWithoutValueMaster) {
        $checkInValueMasterIfEmailAlreadyExists = Http::withHeaders([
            'provider' => 'trolleymaker',
            'password' => 'poiJJ#9q9'
        ])->post('https://valuemaster.brain-behind.com/CU_WebAPI.asmx/CU_checkIfCustomerExists', [
            'searchKey' =>  'E-Mail',
            'searchKeyvalue' => $registerData->TMADMINUSER
        ]);

        if($checkInValueMasterIfEmailAlreadyExists->failed() || $checkInValueMasterIfEmailAlreadyExists == NULL) {
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Es konnte nicht geprüft werden, ob die E-Mail-Adresse bereits registriert wurde. Bitte wenden Sie sich an den Support.', 'error_checking_email_already_exists', 500);
        }

        if($checkInValueMasterIfEmailAlreadyExists && $checkInValueMasterIfEmailAlreadyExists != NULL) {
            $exists_data = json_decode($checkInValueMasterIfEmailAlreadyExists)->d;

            if($exists_data && $exists_data != NULL) {
                if(property_exists($exists_data, 'exists') && $exists_data->exists === true) {
                    return returnNewErrorObject('Es wurde bereits ein Account mit der Firmen-E-Mail-Adresse registriert. Bitte benutzen Sie eine andere E-Mail-Adresse.', 'email_already_exists', 500);
                }
            } else {
                return returnNewErrorObject('Es ist ein Fehler aufgetreten. Es konnte nicht geprüft werden, ob die E-Mail-Adresse bereits registriert wurde. Bitte wenden Sie sich an den Support.', 'error_checking_email_already_exists', 500);
            }
        } else {
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Es konnte nicht geprüft werden, ob die E-Mail-Adresse bereits registriert wurde. Bitte wenden Sie sich an den Support.', 'error_checking_email_already_exists', 500);
        }
    }


    if(strlen($request->input('companyZip')) == 4 || strlen($request->input('companyZip')) == 5) {
        $registerData->companyZip = $request->input('companyZip');
    } else {
        return returnNewErrorObject('Die Postleitzahl darf nur aus 4 oder 5 Zahlen bestehen.', 'invalid_zip', 400);
    }

    $countryForVM = $request->input('companyCountry');

    $registerData->companyName = trim($request->input('companyName'));
    $registerData->companyStreet = trim($request->input('companyStreet'));
    $registerData->companyCity = trim($request->input('companyCity'));
    $registerData->contactPersonRole = trim($request->input('contactPersonRole'));
    $registerData->ceoName = trim($request->input('ceoName'));
    $registerData->ceoPhone = trim($request->input('ceoPhone'));
    $registerData->contactPersonFirstName = $interest_personal_data->CHRISTIANNAME;
    $registerData->contactPersonLastName = $interest_personal_data->NAME;
    $registerData->cardName = $interest_personal_data->NCORTDERANMELDUNG;
    if(!property_exists($interest_personal_data, 'GWGENDER' || empty($interest_personal_data->GWGENDER))) {
        $interest_personal_data->GWGENDER = '';
    }
    $registerData->gender = $interest_personal_data->GWGENDER;

    if($request->has('priceToPay')) {
        $registerData->priceToPay = $request->input('priceToPay');
    } else {
        $registerData->priceToPay = '';
    }

    if($request->has('companyAddressAdditional')) {
        $registerData->companyAddressAdditional = $request->input('companyAddressAdditional');
    } else {
        $registerData->companyAddressAdditional = '';
    }

    $employerCompanyID = '';

    if(!$isRegionWithoutValueMaster) {
        $randomPassword = generateRandomPassword();

        $registerEmployerInValueMaster = Http::withHeaders([
            'provider' => 'trolleymaker',
            'password' => 'poiJJ#9q9'
        ])->post('https://valuemaster.brain-behind.com/CU_WebAPI.asmx/CU_Add_Modify_Partner', [
            'CompanyName' =>  'MA_CARD-' . $registerData->companyName,
            'CompanyID' => 0,
            'active' => '1',
            'InternalID' => $company_data->NCINTERNEID,
            'BusinessSector' => array(),
            'PhoneNumer' => $registerData->ceoPhone,
            'Street' => $registerData->companyStreet,
            'ZIP' => $registerData->companyZip,
            'City' => $registerData->companyCity,
            'Country' => $countryForVM,
            'Language' => 'de',
            'ReceiveStats' => true,
            'ShowPartner' => true,
            'ReceiveInvoice' => true,
            'ChargeTX' => true,
            'CompanyEmail' => $registerData->companyEmail,
            'Web' => '',
            'BankName' => '',
            'IBAN' => '',
            'BIC' => '',
            'latitude' => 0,
            'longitute' => 0,
            'CompanyNameOnInvoice' => $registerData->companyName,
            'CompanyContactPersonOnInvoice' => $registerData->ceoName,
            'InvoiceStreet' => $registerData->companyStreet,
            'InvoiceZIP' => $registerData->companyZip,
            'InvoiceCity' => $registerData->companyCity,
            'InvoiceMail' => $registerData->companyEmail,
            'VATID' => '',
            'logo' => null,
            'Category' => array(),
            'RuleSET' => '',
            'Payment' => 'Invoice',
            'Admin_User' => [
                'Sex' => $interest_personal_data->GWGENDER,
                'PreName' => $interest_personal_data->CHRISTIANNAME,
                'Name' => $interest_personal_data->NAME,
                'LoginEmail' => $registerData->TMADMINUSER,
                'Password' => $randomPassword,
                'SendWelcomeMail' => false
            ]
        ]);

        if($registerEmployerInValueMaster->failed() || $registerEmployerInValueMaster == NULL) {
            Log::Error('Registrierung des Interessenten zum Arbeitgeber ist im ValueMaster fehlgeschlagen: ' . $registerEmployerInValueMaster->body());
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Partner-Account konnte nicht erstellt werden. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
        }

        if($registerEmployerInValueMaster && $registerEmployerInValueMaster != NULL) {
            $employerDataFromValueMaster = json_decode($registerEmployerInValueMaster)->d;

            if($employerDataFromValueMaster && $employerDataFromValueMaster != NULL) {
                if(!property_exists($employerDataFromValueMaster, 'status') || strtolower($employerDataFromValueMaster->status) != 'ok' || !empty($employerDataFromValueMaster->error)) {
                    Log::Error('Registrierung des Interessenten zum Arbeitgeber ist im ValueMaster fehlgeschlagen: ' . $registerEmployerInValueMaster->body());
                    return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Arbeitgeber-Account konnte nicht erstellt werden. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
                }
                if(!property_exists($employerDataFromValueMaster, 'CompanyID') || empty($employerDataFromValueMaster->CompanyID)) {
                    Log::Error('Registrierung des Interessenten zum Arbeitgeber ist im ValueMaster fehlgeschlagen: ' . $registerEmployerInValueMaster->body());
                    return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Arbeitgeber-Account konnte nicht erstellt werden. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
                }
            } else {
                Log::Error('Registrierung des Interessenten zum Arbeitgeber ist im ValueMaster fehlgeschlagen: ' . $registerEmployerInValueMaster->body());
                return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Arbeitgeber-Account konnte nicht erstellt werden. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
            }
        } else {
            Log::Error('Registrierung des Interessenten zum Arbeitgeber ist im ValueMaster fehlgeschlagen: ' . $registerEmployerInValueMaster->body());
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Arbeitgeber-Account konnte nicht erstellt werden. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
        }

        $addTerminalForEmployerResponse = addTerminal(intval($employerDataFromValueMaster->CompanyID), intval($employerDataFromValueMaster->BranchID));
        if(isError($addTerminalForEmployerResponse)) {
            Log::error('Das Terminal konnte nicht angelegt werden.');
            sendErrorNotificationMail('Das Terminal für FirmenID: ' . $employerDataFromValueMaster->CompanyID . ' und BranchID: ' . $employerDataFromValueMaster->BranchID . ' konnte nicht angelegt werden.');
        }

        $employerCompanyID = strval($employerDataFromValueMaster->CompanyID);
    } else {
        $generatePartnerIdResponse = Http::withHeaders([
            'X-API-Key' => config('newRegions.go_backend_api_key'),
        ])->get(config('newRegions.go_backend_url') . '/portals/api/v1/partners/generate-id');

        if($generatePartnerIdResponse->failed() || $generatePartnerIdResponse == NULL) {
            Log::Error('Partner-ID konnte nicht generiert werden: ' . ($generatePartnerIdResponse ? $generatePartnerIdResponse->body() : 'NULL'));
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Arbeitgeber-Account konnte nicht erstellt werden. Bitte wenden Sie sich an den Support.', 'partner_id_generation_failed', 500);
        }

        $partnerIdData = json_decode($generatePartnerIdResponse);
        if(!$partnerIdData || !property_exists($partnerIdData, 'partnerId') || empty($partnerIdData->partnerId)) {
            Log::Error('Partner-ID konnte nicht aus der Antwort gelesen werden: ' . $generatePartnerIdResponse->body());
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Arbeitgeber-Account konnte nicht erstellt werden. Bitte wenden Sie sich an den Support.', 'partner_id_generation_failed', 500);
        }

        $employerCompanyID = strval($partnerIdData->partnerId);
    }


    $dateNow = new DateTime('now');
    $dateNow->setTimezone(new DateTimeZone('Europe/Berlin'));
    $registerData->registeredSince = $dateNow->format('Y-m-d\TH:i:s');

    $fieldsToUpdate = new stdClass();
    $fieldsToUpdate->GWSTYPE = 'Partnerschaft';
    $fieldsToUpdate->TMARTDERPARTNERSCHAFT = "Partner";
    $fieldsToUpdate->TMMODULEPARTNER = 'MitarbeiterCARD';
    $fieldsToUpdate->MAILFIELDSTR4 = $registerData->companyEmail;
    $fieldsToUpdate->TMFIRMENINHABER = $registerData->ceoName;
    $fieldsToUpdate->PHONEFIELDSTR9 = $registerData->ceoPhone;
    $fieldsToUpdate->GWADDITIONALINFO1 = $registerData->companyAddressAdditional;
    $fieldsToUpdate->STREET1 = $registerData->companyStreet;
    $fieldsToUpdate->NCRESTREET = $registerData->companyStreet;
    $fieldsToUpdate->TOWN1 = $registerData->companyCity;
    $fieldsToUpdate->NCREORT = $registerData->companyCity;
    $fieldsToUpdate->ZIP1 = $registerData->companyZip;
    $fieldsToUpdate->NCREZIP = $registerData->companyZip;
    $fieldsToUpdate->COUNTRY1 = $registerData->companyCountry;
    $fieldsToUpdate->TMRELAND = $registerData->companyCountry;
    $fieldsToUpdate->COMPNAME = $registerData->companyName;
    $fieldsToUpdate->NCREFIRMA = $registerData->companyName;
    $fieldsToUpdate->NCINTERESSENTPWD = NULL;
    $fieldsToUpdate->NCFIRMENID = $employerCompanyID;
    $fieldsToUpdate->TMEINGANGAUFTRAGPARTNER = $registerData->registeredSince;
    $fieldsToUpdate->TMVERTRAGSDATUM = $registerData->registeredSince;
    $fieldsToUpdate->TMBETRAGVERTRAGSABSCHLUSS = intval($registerData->priceToPay);
    $fieldsToUpdate->TMVERTRAGSBUNDLE = false;
    $fieldsToUpdate->TMVERTRAGSSTATUSPARTNER = 'aktiv';
    if($request->has('additionalNotesForContract') && !empty($request->input('additionalNotesForContract'))) {
        $registerData->additionalNotesForContract = $request->input('additionalNotesForContract');
        $fieldsToUpdate->TMHINWEISEZUMVERTRAG = $registerData->additionalNotesForContract;
    } else {
        $fieldsToUpdate->TMHINWEISEZUMVERTRAG = '';
    }


    if(!updateGwAddressData($company_data->GGUID, $fieldsToUpdate) || !updateGwAddressData($interest_personal_data->GGUID, ['NCINTERESSENTPWD' => NULL, 'TMANSPRECHPARTNERFUER' => 'MitarbeiterCARD,Vertrag,Rechnung,Technik'])) {
        return returnNewErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
    } else {
        $dateNow = new DateTime('now');
        $dateNow->setTimezone(new DateTimeZone('Europe/Berlin'));
        $registerData->registeredSince = $dateNow->format('d.m.Y H:i:s');

        $partner_user_role = getPartnerRolle('Admin');
        DB::table('mycitycards_sessions')
            ->where('id', $request->input('session_id'))
            ->update(['partner_user_role' => $partner_user_role, 'user_role' => UserRoles::EMPLOYER, 'company_id' => $fieldsToUpdate->NCFIRMENID]);


        Mail::to($registerData->companyEmail)->send(new RegistrationEmployerCustomerMail($registerData));
        Mail::to('mitarbeitercard@trolleymaker.com')->cc(['vertrieb@trolleymaker.com'])->send(new RegistrationEmployerMail($registerData));
        return response()->json( $registerData, 200 );
    }
})->middleware(['AuthenticateWithSession']);


Route::get('/employer-complete-personal-data', function (Request $request) {

    if(!$request->input('email')) {
        return returnNewErrorObject('Es wurde keine E-Mail angegeben!', 'no_email', 400);
    }

    $personal_data = getGwPersonalDataByGGUID($request->input('contact_person_gguid'));
    $company_data = getGwPersonalDataByGGUID($request->input('company_gguid'));

    if(!property_exists($personal_data, 'GGUID')) {
        return returnNewErrorObject('Der Benutzer wurde nicht gefunden. Bitte wenden Sie sich an den Support.', 'user_not_found', 400);
    }

    if(!property_exists($company_data, 'GGUID')) {
        return returnNewErrorObject('Es wurde keine Firma gefunden. Bitte wenden Sie sich an den Support.', 'user_not_found', 400);
    }

    if(!property_exists($company_data, 'NCINTERNEID') || empty($company_data->NCINTERNEID) || !property_exists($company_data, 'TMVERTRAGID') || empty($company_data->TMVERTRAGID)) {
        return returnNewErrorObject('Ihre Vertragsnummer wurde nicht gefunden. Bitte wenden Sie sich an den Support!', 'no_contract_id', 500);
    }


    if(isError($personal_data)) {
        return returnErrorObject($personal_data);
    }

    if(isError($company_data)) {
        return returnErrorObject($company_data);
    }

    if(!property_exists($personal_data, 'TMPARTNERPORTALROLLE')) {
        return returnNewErrorObject('Es wurde keine Benutzerrolle für Ihren Benutzer gefunden. Bitte wenden Sie sich an den Support.', 'no_user_role', 400);
    }


    $responseToSend = new stdClass();
    $responseToSend->contactPersonGender = property_exists($personal_data, 'GWGENDER') ? $personal_data->GWGENDER : '';
    $responseToSend->contactPersonFirstName = $personal_data->CHRISTIANNAME;
    $responseToSend->contactPersonLastName = $personal_data->NAME;
    $responseToSend->contactPersonEmail = $personal_data->TMADMINUSER;

    $responseToSend->partnerDataComplete = $company_data->TMPARTNERDATENVOLLSTAENDIG;
    $responseToSend->companyName = $company_data->COMPNAME;
    $responseToSend->companyAddressAdditional = property_exists($company_data, 'GWADDITIONALINFO1') ? $company_data->GWADDITIONALINFO1 : '';
    $responseToSend->companyStreet = property_exists($company_data, 'STREET1') ? $company_data->STREET1 : NULL;
    $responseToSend->companyZip = property_exists($company_data, 'ZIP1') ? $company_data->ZIP1 : NULL;
    $responseToSend->companyCity = property_exists($company_data, 'TOWN1') ? $company_data->TOWN1 : NULL;
    $responseToSend->companyCountry = property_exists($company_data, 'COUNTRY1') ? $company_data->COUNTRY1 : NULL;
    $responseToSend->sepaMandateReferenceNumber = $company_data->NCINTERNEID;
    $responseToSend->companyGewerbeverein = property_exists($company_data, 'TMGEWERBEVEREININFOMITGLIED') ? $company_data->TMGEWERBEVEREININFOMITGLIED : NULL;
    $responseToSend->companyEmail = property_exists($company_data, 'MAILFIELDSTR4') ? $company_data->MAILFIELDSTR4 : NULL;
    $responseToSend->companyEmailRepeated = $responseToSend->companyEmail;
    $responseToSend->companyEmailHeadquarter = property_exists($company_data, 'MAILFIELDSTR5') ? $company_data->MAILFIELDSTR5 : $company_data->MAILFIELDSTR4;
    $responseToSend->companyEmailHeadquarterRepeated = $responseToSend->companyEmailHeadquarter;
    $responseToSend->companyREName = property_exists($company_data, 'NCREFIRMA') ? $company_data->NCREFIRMA : $company_data->COMPNAME;
    $responseToSend->companyREZip = property_exists($company_data, 'NCREZIP') ? $company_data->NCREZIP : $company_data->ZIP1;
    $responseToSend->companyREStreet = property_exists($company_data, 'NCRESTREET') ? $company_data->NCRESTREET : $company_data->STREET1;
    $responseToSend->companyRECity = property_exists($company_data, 'NCREORT') ? $company_data->NCREORT : $company_data->TOWN1;
    $responseToSend->companyRECountry = property_exists($company_data, 'TMRELAND') ? $company_data->TMRELAND : $company_data->COUNTRY1;
    $responseToSend->companyREEmail = property_exists($company_data, 'TMMAILRECHNUNG') ? $company_data->TMMAILRECHNUNG : $company_data->MAILFIELDSTR4;
    $responseToSend->companyREEmailRepeated = $responseToSend->companyREEmail;
    $responseToSend->ceoName = property_exists($company_data, 'TMFIRMENINHABER') ? $company_data->TMFIRMENINHABER : NULL;
    $responseToSend->ceoPhone = property_exists($company_data, 'PHONEFIELDSTR9') ? $company_data->PHONEFIELDSTR9 : NULL;

    $responseToSend->amountOfEmployerCards = property_exists($company_data, 'TMANZAHLMITARBEITERCARDS') ? $company_data->TMANZAHLMITARBEITERCARDS : '';
    $responseToSend->wantIndividualEmployerCards = property_exists($company_data, 'TMINDIVIDUELLEMITARBEITERCARDS') ? $company_data->TMINDIVIDUELLEMITARBEITERCARDS : false;
    $responseToSend->employerCardStartDate = property_exists($company_data, 'TMSTARTMC') ? gWDateToHtmlDate($company_data->TMSTARTMC) : NULL;
    $responseToSend->employerCardsLoadingRhythm = property_exists($company_data, 'TMBELADUNGSRHYTHMUS') ? $company_data->TMBELADUNGSRHYTHMUS : NULL;
    $responseToSend->employerCardsLoadingDate = property_exists($company_data, 'TMBELADUNGSTERMIN') ? $company_data->TMBELADUNGSTERMIN : NULL;

    $responseToSend->paymentMethod = property_exists($company_data, 'NCARTABRECHNUNG') ? $company_data->NCARTABRECHNUNG : NULL;
    $responseToSend->sepaBIC = property_exists($company_data, 'GWBIC') ? $company_data->GWBIC : NULL;
    $responseToSend->sepaIBAN = property_exists($company_data, 'GWIBAN') ? $company_data->GWIBAN : NULL;
    $responseToSend->sepaBankName = property_exists($company_data, 'FINANCIALINSTITUTE') ? $company_data->FINANCIALINSTITUTE : NULL;
    $responseToSend->sepaAccountHolder = property_exists($company_data, 'BANKACCOUNTHOLDER') ? $company_data->BANKACCOUNTHOLDER : NULL;
    $responseToSend->sepaCompanyStreet = $responseToSend->companyREStreet;
    $responseToSend->sepaCompanyZip = $responseToSend->companyREZip;
    $responseToSend->sepaCompanyCity = $responseToSend->companyRECity;
    $responseToSend->contactDetailsSentAt = property_exists($company_data, 'TMEINGANGAUFTRAGPARTNER') ? gWDateToGermanDateAndTime($company_data->TMEINGANGAUFTRAGPARTNER) : '-';
    $responseToSend->contractStartAt = property_exists($company_data, 'TMVERTRAGSDATUM') ? gWDateToGermanDate($company_data->TMVERTRAGSDATUM) : '-';
    $responseToSend->contractStatus = property_exists($company_data, 'TMVERTRAGSSTATUSPARTNER') ? $company_data->TMVERTRAGSSTATUSPARTNER : '-';
    $responseToSend->contractID = property_exists($company_data, 'TMVERTRAGID') ? $company_data->TMVERTRAGID : '-';

    $responseToSend->community = property_exists($company_data, 'TMGEMEINDEZUGEHOERIGKEIT') ? $company_data->TMGEMEINDEZUGEHOERIGKEIT : NULL;

    return response()->json( $responseToSend, 200 );

})->middleware(['AuthenticateWithSession']);


Route::post('/employer-personal-data', function (Request $request) {

    foreach(array('companyName', 'companyStreet', 'companyZip', 'companyCity', 'companyCountry', 'contactPersonGender', 'contactPersonFirstName', 'contactPersonLastName',
                  'companyREName', 'companyREStreet', 'companyREZip', 'companyRECity', 'companyRECountry', 'companyREEmail', 'ceoName', 'ceoPhone',
            ) as $input) {
        if($request->input($input) == NULL || $request->input($input) == '') {
            return returnNewErrorObject('Es wurden nicht alle erforderlichen Felder ausgefüllt!' . $input, 'missing_fields', 400);
        }
    }

    $company_data = getGwPersonalDataByGGUID($request->input('company_gguid'));
    if(!property_exists($company_data, 'GGUID')) {
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Die Firma wurde nicht gefunden. Bitte kontaktieren Sie den Support.', 'no_company', 400);
    }

    $personal_data = getGwPersonalDataByGGUID($request->input('contact_person_gguid'));
    if(!property_exists($personal_data, 'GGUID')) {
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Der Ansprechpartner wurde nicht gefunden. Bitte kontaktieren Sie den Support.', 'no_contact_person', 400);
    }

    //update contact person
    $contactPersonFieldsToUpdate = new stdClass();
    $contactPersonFieldsToUpdate->GWGENDER = $request->input('contactPersonGender');
    $contactPersonFieldsToUpdate->CHRISTIANNAME = $request->input('contactPersonFirstName');
    $contactPersonFieldsToUpdate->NAME = $request->input('contactPersonLastName');
    if(!updateGwAddressData($request->input('contact_person_gguid'), $contactPersonFieldsToUpdate)) {
        Log::Error('Bei /employer-personal-data ist ein Fehler aufgetreten. Der Ansprechpartner konnte nicht geupdatet werden.');
        return returnNewErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
    }


    //update company
    $companyFieldsToUpdate = new stdClass();
    $companyFieldsToUpdate->COMPNAME = $request->input('companyName');
    $companyFieldsToUpdate->GWADDITIONALINFO1 = $request->input('companyAddressAdditional');
    $companyFieldsToUpdate->STREET1 = $request->input('companyStreet');
    $companyFieldsToUpdate->TOWN1 = $request->input('companyCity');
    $companyFieldsToUpdate->ZIP1 = $request->input('companyZip');
    $companyFieldsToUpdate->COUNTRY1 = $request->input('companyCountry');
    $companyFieldsToUpdate->MAILFIELDSTR4 = $request->input('companyEmail');
    $companyFieldsToUpdate->MAILFIELDSTR5 = $request->input('companyEmailHeadquarter');
    $companyFieldsToUpdate->NCREFIRMA = $request->input('companyREName');
    $companyFieldsToUpdate->NCREZIP = $request->input('companyREZip');
    $companyFieldsToUpdate->NCRESTREET = $request->input('companyREStreet');
    $companyFieldsToUpdate->NCREORT = $request->input('companyRECity');
    $companyFieldsToUpdate->TMRELAND = $request->input('companyRECountry');
    $companyFieldsToUpdate->TMMAILRECHNUNG = $request->input('companyREEmail');
    $companyFieldsToUpdate->TMFIRMENINHABER = $request->input('ceoName');
    $companyFieldsToUpdate->PHONEFIELDSTR9 = $request->input('ceoPhone');

    //dont allow updating the following fields, so only update if not already set
    if(!property_exists($company_data, 'TMANZAHLMITARBEITERCARDS') || $company_data->TMANZAHLMITARBEITERCARDS == '' || $company_data->TMANZAHLMITARBEITERCARDS == NULL) {
        $companyFieldsToUpdate->TMANZAHLMITARBEITERCARDS = strval($request->input('amountOfEmployerCards'));
    }
    if(!property_exists($company_data, 'TMINDIVIDUELLEMITARBEITERCARDS') || $company_data->TMINDIVIDUELLEMITARBEITERCARDS == '' || $company_data->TMINDIVIDUELLEMITARBEITERCARDS == NULL) {
        $companyFieldsToUpdate->TMINDIVIDUELLEMITARBEITERCARDS = $request->has('wantIndividualEmployerCards') ? $request->input('wantIndividualEmployerCards') : false;
    }
    if(!property_exists($company_data, 'TMSTARTMC') || $company_data->TMSTARTMC == '' || $company_data->TMSTARTMC == NULL) {
        $companyFieldsToUpdate->TMSTARTMC = htmlDateToGwDate($request->input('employerCardStartDate'));
    }
    if(!property_exists($company_data, 'TMBELADUNGSRHYTHMUS') || $company_data->TMBELADUNGSRHYTHMUS == '' || $company_data->TMBELADUNGSRHYTHMUS == NULL) {
        $companyFieldsToUpdate->TMBELADUNGSRHYTHMUS = $request->input('employerCardsLoadingRhythm');
    }
    if(!property_exists($company_data, 'TMBELADUNGSTERMIN') || $company_data->TMBELADUNGSTERMIN == '' || $company_data->TMBELADUNGSTERMIN == NULL) {
        $companyFieldsToUpdate->TMBELADUNGSTERMIN = $request->input('employerCardsLoadingDate');
    }

    if(property_exists($company_data, 'NCARTABRECHNUNG') && $company_data->NCARTABRECHNUNG != '') {
        //dont allow updating payment method, so use existing payment method and not from request
        $companyFieldsToUpdate->NCARTABRECHNUNG = $company_data->NCARTABRECHNUNG;
    } else {
        $companyFieldsToUpdate->NCARTABRECHNUNG = $request->input('paymentMethod');
    }
    $companyFieldsToUpdate->GWBIC = strtoupper($request->input('sepaBIC'));
    $companyFieldsToUpdate->GWIBAN = $request->input('sepaIBAN');
    $companyFieldsToUpdate->FINANCIALINSTITUTE = $request->input('sepaBankName');
    $companyFieldsToUpdate->BANKACCOUNTHOLDER = $request->input('sepaAccountHolder');

    if(!updateGwAddressData($request->input('company_gguid'), $companyFieldsToUpdate)) {
        Log::Error('Bei /employer-personal-data ist ein Fehler aufgetreten. Die Firma konnte nicht geupdatet werden.');
        return returnNewErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
    }

    $vmPayment = 'SEPA_DirectDebit';
    if($companyFieldsToUpdate->NCARTABRECHNUNG == 'Bankeinzug') {
        $vmPayment = 'SEPA_DirectDebit';
    } else if($companyFieldsToUpdate->NCARTABRECHNUNG == 'Rechnung') {
        $vmPayment = 'Invoice';
    }


    $countryForVM = $request->input('companyCountry');

    $vmCompanyFieldsToSet = new stdClass();
    $vmCompanyFieldsToSet->companyName = 'MA_CARD-' . $companyFieldsToUpdate->COMPNAME;
    $vmCompanyFieldsToSet->companyID = intval($company_data->NCFIRMENID);
    $vmCompanyFieldsToSet->active = '1';
    $vmCompanyFieldsToSet->internalID = $company_data->NCINTERNEID;
    $vmCompanyFieldsToSet->phoneNumber = $companyFieldsToUpdate->PHONEFIELDSTR9;
    $vmCompanyFieldsToSet->street = $companyFieldsToUpdate->STREET1;
    $vmCompanyFieldsToSet->zip = $companyFieldsToUpdate->ZIP1;
    $vmCompanyFieldsToSet->city = $companyFieldsToUpdate->TOWN1;
    $vmCompanyFieldsToSet->country = $countryForVM;
    $vmCompanyFieldsToSet->companyEmail = $companyFieldsToUpdate->MAILFIELDSTR4;
    $vmCompanyFieldsToSet->bankName = $companyFieldsToUpdate->FINANCIALINSTITUTE;
    $vmCompanyFieldsToSet->iban = $companyFieldsToUpdate->GWIBAN;
    $vmCompanyFieldsToSet->bic = strtoupper($companyFieldsToUpdate->GWBIC);
    $vmCompanyFieldsToSet->companyNameOnInvoice = $companyFieldsToUpdate->NCREFIRMA;
    $vmCompanyFieldsToSet->companyContactPersonOnInvoice = $companyFieldsToUpdate->TMFIRMENINHABER;
    $vmCompanyFieldsToSet->invoiceStreet = $companyFieldsToUpdate->NCRESTREET;
    $vmCompanyFieldsToSet->invoiceZIP = $companyFieldsToUpdate->NCREZIP;
    $vmCompanyFieldsToSet->invoiceCity = $companyFieldsToUpdate->NCREORT;
    $vmCompanyFieldsToSet->invoiceMail = $companyFieldsToUpdate->TMMAILRECHNUNG;
    $vmCompanyFieldsToSet->payment = $vmPayment;

    Log::debug(print_r($vmCompanyFieldsToSet, true));

    $updateCompanyEmployerInValueMaster = addOrModifyPartnerInValueMaster($vmCompanyFieldsToSet);

    if($updateCompanyEmployerInValueMaster->failed() || $updateCompanyEmployerInValueMaster == NULL) {
        Log::Error('Updaten des Arbeitgeber ist im ValueMaster fehlgeschlagen: ' . $updateCompanyEmployerInValueMaster->body());
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Arbeitgeber-Account konnte nicht aktualisiert werden. Bitte wenden Sie sich an den Support.', 'update_employer_error', 500);
    }

    if($updateCompanyEmployerInValueMaster && $updateCompanyEmployerInValueMaster != NULL) {
        $companyEmployerDataFromValueMaster = json_decode($updateCompanyEmployerInValueMaster)->d;

        if($companyEmployerDataFromValueMaster && $companyEmployerDataFromValueMaster != NULL) {
            if(!property_exists($companyEmployerDataFromValueMaster, 'status') || strtolower($companyEmployerDataFromValueMaster->status) != 'ok' || !empty($companyEmployerDataFromValueMaster->error)) {
                Log::Error('Updaten des Arbeitgeber ist im ValueMaster fehlgeschlagen: ' . $updateCompanyEmployerInValueMaster->body());
                return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Arbeitgeber-Account konnte nicht aktualisiert werden. Bitte wenden Sie sich an den Support.', 'update_employer_error', 500);
            }
            if(!property_exists($companyEmployerDataFromValueMaster, 'CompanyID')) {
                Log::Error('Updaten des Arbeitgeber ist im ValueMaster fehlgeschlagen: ' . $updateCompanyEmployerInValueMaster->body());
                return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Arbeitgeber-Account konnte nicht aktualisiert werden. Bitte wenden Sie sich an den Support.', 'update_employer_error', 500);
            }
        } else {
            Log::Error('Updaten des Arbeitgeber ist im ValueMaster fehlgeschlagen: ' . $updateCompanyEmployerInValueMaster->body());
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Arbeitgeber-Account konnte nicht aktualisiert werden. Bitte wenden Sie sich an den Support.', 'update_employer_error', 500);
        }
    } else {
        Log::Error('Updaten des Arbeitgeber ist im ValueMaster fehlgeschlagen: ' . $updateCompanyEmployerInValueMaster->body());
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Arbeitgeber-Account konnte nicht aktualisiert werden. Bitte wenden Sie sich an den Support.', 'update_employer_error', 500);
    }

    if(!property_exists($company_data, 'TMPARTNERDATENVOLLSTAENDIG') || $company_data->TMPARTNERDATENVOLLSTAENDIG === false || $company_data->TMPARTNERDATENVOLLSTAENDIG === '') {
        if(!updateGwAddressData($request->input('company_gguid'), ['TMPARTNERDATENVOLLSTAENDIG' => true])) {
            Log::Error('Bei /employer-personal-data ist ein Fehler aufgetreten. Die FIrma konnte nicht geupdatet werden, das TMPARTNERDATENVOLLSTAENDIG konnte zum Schluss nicht gesetzt werden.');
            return returnNewErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
        }

        $registerData = new stdClass();
        $registerData->companyName = $companyFieldsToUpdate->COMPNAME;
        $registerData->companyEmail = $companyFieldsToUpdate->MAILFIELDSTR4;
        $registerData->cardName = $personal_data->NCORTDERANMELDUNG;
        Mail::to('mitarbeitercard@trolleymaker.com')->send(new PersonalDataCompleteEmployerMail($registerData));
    }

    return response()->json( new stdClass(), 200 );

})->middleware(['AuthenticateWithSession']);


Route::post('/set-logo', function (Request $request) {

    if(!$request->hasFile('logo')) {
        return returnNewErrorObject('Es wurde keine Datei angegeben.', 'missing_file', 400);
    }

    if(!$request->file('logo')->isValid()) {
        return returnNewErrorObject('Beim Hochladen ist ein Fehler aufgetreten.', 'corrupted_file', 400);
    }

    $logoStoredAt = $request->logo->store('temp-logos');
    $logoPath = storage_path('app/' . $logoStoredAt);

    if(!updateGwLogo($request->input('company_gguid'), $logoPath)) {
        Log::Error('Bei /set Logo ist ein Fehler aufgetreten.');
        return returnNewErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
    }

    return response()->json( new stdClass(), 200 );

})->middleware(['AuthenticateWithSession']);



Route::post('/get-logo', function (Request $request) {

    $gwGetLogo = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->get(env('GW_API_BASE') . '/type/address/' . $request->input('company_gguid') . '/image');

    if($gwGetLogo->successful()) {
        $logoBase64 = base64_encode($gwGetLogo);
        return response($logoBase64);
    }

    if($gwGetLogo->failed()) {
        if($gwGetLogo->status() == 503) {
            return returnNewErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'server_unavailable', 503 );
        } else if($gwGetLogo->status() == 404) {
            return returnNewErrorObject('Es wurde kein Logo gefunden.', 'logo_not_found', 400);
        } else {
            Log::error("Fehler beim Abrufen von gwGetLogo: " . print_r($gwGetLogo, true));
            return returnNewErrorObject('Das Logo konnte nicht abgerufen werden.', 'error_fetching_logo', 400);
        }
    }

    return response();

})->middleware(['AuthenticateWithSession']);


Route::post('/add-employercards-list', function (Request $request) {

    if(!$request->hasFile('employerCardsListFile')) {
        return returnNewErrorObject('Es wurde keine Datei angegeben.', 'missing_file', 400);
    }

    if(!$request->file('employerCardsListFile')->isValid()) {
        return returnNewErrorObject('Beim Hochladen ist ein Fehler aufgetreten.', 'corrupted_file', 400);
    }

    //$employerCardsListFileStoredAt = $request->employerCardsListFile->store('temp-files');
    //$employerCardsListFilePath = storage_path('app/' . $employerCardsListFileStoredAt);

    $addDocument = Http::withBody(file_get_contents($request->employerCardsListFile), 'image/png')->withHeaders([
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA==',
        'CAS-FILE-EXTENSION' => $request->employerCardsListFile->extension()
    ])->post(env('GW_API_BASE') . '/type/document/file');

    if($addDocument->failed()) {
        if($addDocument->status() == 503) {
            return returnNewErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'server_unavailable', 503 );
        } else {
            Log::error("Fehler beim Speichern von /add-employercards-list: " . print_r($addDocument->body(), true));
            return returnNewErrorObject('Das Dokument konnte nicht abgerufen werden.', 'error_saving_document', 400);
        }
    }

    $location_splitted = explode("/", $addDocument->header('Location'));
    $documentGGUID = $location_splitted[count($location_splitted)-2];


    $company_data = getGwPersonalDataByGGUID($request->input('company_gguid'));
    $company_name = '';
    if(property_exists($company_data, 'GGUID') && property_exists($company_data, 'COMPNAME')) {
        $company_name = $company_data->COMPNAME;
    }

    $documentFieldsToUpdate = new stdClass();
    $documentFieldsToUpdate->GWSTYPE = 'Ladeliste';
    $documentFieldsToUpdate->GWSSTATUS = 'empfangen';
    $documentFieldsToUpdate->CATEGORY = 'MitarbeiterCARD';
    $documentFieldsToUpdate->KEYWORD = date('Ymd') . ' Ladeliste ' . $company_name;

    $updatedDocumentResponse = updateGwDocumentData($documentGGUID, $documentFieldsToUpdate);
    if(isError($updatedDocumentResponse)) {
        return returnErrorObject($updatedDocumentResponse);
    }

    if(property_exists($company_data, 'GGUID')) {
        $addGwLink = Http::withHeaders([
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
            'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
        ])->post(env('GW_API_BASE') . '/type/DOCUMENT/' . $documentGGUID . '/dossier?gguid2=' . $company_data->GGUID . '&attribute=ITDDOCADR&object-type2=ADDRESS');

        if($addGwLink->failed()) {
            Log::error("Fehler beim Erstellen einer neuen Verknüpfung von Ladeliste Document zu Adresse: " . $addGwLink->body());
            sendErrorNotificationMail("Fehler beim Erstellen einer neuen Verknüpfung von Ladeliste Document zu Adresse: " . $addGwLink->body());
        }
    } else {
        Log::error("Fehler beim Erstellen einer neuen Verknüpfung von Ladeliste Document zu Adresse: GGUID bzw. Company Datensatz wurde nicht gefunden.");
        sendErrorNotificationMail("Fehler beim Erstellen einer neuen Verknüpfung von Ladeliste Document zu Adresse: GGUID bzw. Company Datensatz wurde nicht gefunden.");
    }

    return response()->json( new stdClass(), 200 );

})->middleware(['AuthenticateWithSession']);


Route::get('/partner-employer-documents', function (Request $request) {

    $documents = getDocumentsForCompany($request->input('company_gguid'), ['abrechnung', 'rechnung'], 'pdf', 'versendet');

    if(isError($documents)) {
        return returnErrorObject($documents);
    }

    return response()->json( $documents, 200 );

})->middleware(['AuthenticateWithSession']);


Route::post('/partner-employer-documents', function (Request $request) {

    if(!$request->has('document') || $request->input('document') === '') {
        Log::error('Beim Dokument Download wurde keine Document GGUID mitgeschickt');
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 400);
    }

    $document_gguid = $request->input('document');

    $gwGetDocument = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->get(env('GW_API_BASE') . '/type/document/0x' . $document_gguid . '/file');

    if($gwGetDocument->successful()) {
        return response($gwGetDocument);
    }

    if($gwGetDocument->failed()) {
        if($gwGetDocument->status() == 503) {
            return returnNewErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'server_unavailable', 503 );
        } else if($gwGetDocument->status() == 404) {
            return returnNewErrorObject('Es wurde kein Dokument gefunden.', 'document_not_found', 400);
        } else {
            Log::error("Fehler beim Abrufen von gwGetDocument: " . print_r($gwGetDocument, true));
            return returnNewErrorObject('Das Dokument konnte nicht abgerufen werden.', 'error_fetching_document', 400);
        }
    }

})->middleware(['AuthenticateWithSession']);

Route::post('/batch-guthaben-einloesen', function (Request $request) {

    if(!$request->has('inputCardIDs') || !is_array($request->input('inputCardIDs')) || count($request->input('inputCardIDs')) == 0) {
        return returnNewErrorObject('Es wurden keine Kartennummern angegeben.', 'no_cardIDs', 400 );
    }

    $failedCardIDs = [];

    $inputGuthabenEinloesenBetrag = $request->input('guthabenEinloesenBetrag');
    $amountCent = getAmountCentForBetragInput($inputGuthabenEinloesenBetrag);

    if(!_isValidAmountCent($amountCent)) {
        return returnNewErrorObject('Ungültiger Einlösungsbetrag.', 'invalid_amount_cent', 400);
    }

    if(!_isValidAmountCent($amountCent)) {
        return returnNewErrorObject('Ungültiger Bonusbetrag', 'invalid_bonus_amount', 400);
    }

    foreach ($request->input('inputCardIDs') as $inputCardID) {

        $returnFromHandle = _redeemVoucher($request, $inputCardID, $amountCent);

        if(isError($returnFromHandle)){
            array_push($failedCardIDs, $inputCardID);
        }

        sleep(1); #prevent system overload
    }

    if(count($failedCardIDs) > 0){
        $errObj = createErrorObject('not successfull for all cardIDs', 'error_on_batch', 400);
        $errObj->failedCardIDs = $failedCardIDs;
        return returnErrorObject($errObj);
    }
    return response()->json( new stdClass(), 200 );

})->middleware(['AuthenticateWithSession']);


Route::post('/partner-guthaben-einloesen', function (Request $request) {

    if(!$request->has('inputCardID') || $request->input('inputCardID') == '' || strlen($request->input('inputCardID')) == 0) {
        return returnNewErrorObject('Es wurde keine Kartennummer angegeben.', 'no_cardID', 400 );
    }

    if(!$request->has('guthabenEinloesenBetrag') || $request->input('guthabenEinloesenBetrag') == '' || strlen($request->input('guthabenEinloesenBetrag')) == 0) {
        return returnNewErrorObject('Sie müssen einen Betrag zum Guthaben einlösen eingeben.',  'no_voucher_amount', 400 );
    }

    $inputCardID = trim($request->input('inputCardID'));

    $inputGuthabenEinloesenBetrag = $request->input('guthabenEinloesenBetrag');
    $amountCent = getAmountCentForBetragInput($inputGuthabenEinloesenBetrag);

    if(!_isValidAmountCent($amountCent)) {
        return returnNewErrorObject('Ungültiger Einlösungsbetrag.', 'invalid_amount_cent', 400);
    }

    $returnFromHandle = _redeemVoucher($request, $inputCardID, $amountCent);

    if(isError($returnFromHandle)){
        return returnErrorObject($returnFromHandle);
    }

    return response()->json( $returnFromHandle, 200 );

})->middleware(['AuthenticateWithSession', 'AuthenticateIsPartnerAdminOrUser']);


Route::post('/batch-kundenbonus-aufladen', function (Request $request) {
    if(!$request->has('inputCardIDs') || !is_array($request->input('inputCardIDs')) || count($request->input('inputCardIDs')) == 0) {
        return returnNewErrorObject('Es wurde keine Kartennummer angegeben.', 'missing_cardIDs', 400 );
    }

    $failedCardIDs = [];

    $inputKundenbonusAufladenBetrag = $request->input('kundenbonusAufladenBetrag');
    $amountCent = (int) getAmountCentForBetragInput($inputKundenbonusAufladenBetrag);
    if(!_isValidAmountCent($amountCent)) {
        return returnNewErrorObject('Ungültiger Einkaufsbetrag', 'invalid_amount_cent', 400);
    }

    foreach ($request->input('inputCardIDs') as $inputCardID) {

        $returnFromHandle = _addBonus($request, $inputCardID, $amountCent);

        if(isError($returnFromHandle)){
            array_push($failedCardIDs, $inputCardID);
        }

        sleep(1); #prevent system overload
    }

    if(count($failedCardIDs) > 0){
        $errObj = createErrorObject('not successfull for all cardIDs', 'error_on_batch', 400);
        $errObj->failedCardIDs = $failedCardIDs;
        return returnErrorObject($errObj);
    }
    return response()->json( new stdClass(), 200 );
})->middleware(['AuthenticateWithSession']);


Route::post('/partner-kundenbonus-aufladen', function (Request $request) {

    if(!$request->has('inputCardID') || $request->input('inputCardID') == '' || strlen($request->input('inputCardID')) == 0) {
        return returnNewErrorObject('Es wurde keine Kartennummer angegeben.', 'no_cardID', 400 );
    }

    if(!$request->has('kundenbonusAufladenBetrag') || $request->input('kundenbonusAufladenBetrag') == '' || strlen($request->input('kundenbonusAufladenBetrag')) == 0) {
        return returnNewErrorObject('Sie müssen einen Betrag zum Kundenbonus aufladen eingeben.', 'no_amount_cent', 400 );
    }

    $cardID = trim($request->input('inputCardID'));

    $inputKundenbonusAufladenBetrag = $request->input('kundenbonusAufladenBetrag');
    $amountCent = (int) getAmountCentForBetragInput($inputKundenbonusAufladenBetrag);
    if(!_isValidAmountCent($amountCent)) {
        return returnNewErrorObject('Ungültiger Einkaufsbetrag', 'invalid_amount_cent', 400);
    }

    $returnFromHandle = _addBonus($request, $cardID, $amountCent);

    if(isError($returnFromHandle)){
        return returnErrorObject($returnFromHandle);
    }

    return response()->json( $returnFromHandle, 200 );

})->middleware(['AuthenticateWithSession', 'AuthenticateIsPartnerAdminOrUser']);


Route::post('/batch-guthaben-aufladen', function (Request $request) {
    if(!$request->has('inputCardIDs') || !is_array($request->input('inputCardIDs')) || count($request->input('inputCardIDs')) == 0) {
        return returnNewErrorObject('Es wurde keine Kartennummer angegeben.', 'missing_cardIDs', 400 );
    }

    $failedCardIDs = [];

    $inputguthabenAufladenBetrag = $request->input('guthabenAufladenBetrag');
    $amountCent = (int) getAmountCentForBetragInput($inputguthabenAufladenBetrag);

    if(!_isValidAmountCent($amountCent)) {
        return returnNewErrorObject('Ungültiger Aufladungsbetrag.', 'invalid_amount_cent', 400);
    }

    foreach ($request->input('inputCardIDs') as $inputCardID) {

        $returnFromHandle = _addVoucher($request, $inputCardID, $amountCent);

        if(isError($returnFromHandle)){
            array_push($failedCardIDs, $inputCardID);
        }

        sleep(1); #prevent system overload
    }

    if(count($failedCardIDs) > 0){
        $errObj = createErrorObject('not successfull for all cardIDs', 'error_on_batch', 400);
        $errObj->failedCardIDs = $failedCardIDs;
        return returnErrorObject($errObj);
    }
    return response()->json( new stdClass(), 200 );
})->middleware(['AuthenticateWithSession']);

Route::post('/partner-guthaben-aufladen', function (Request $request) {

    if(!$request->has('inputCardID') || $request->input('inputCardID') == '' || strlen($request->input('inputCardID')) == 0) {
        return returnNewErrorObject('Es wurde keine Kartennummer angegeben.', 'no_cardID', 400);
    }

    if(!$request->has('guthabenAufladenBetrag') || $request->input('guthabenAufladenBetrag') == '' || strlen($request->input('guthabenAufladenBetrag')) == 0) {
        return returnNewErrorObject('Sie müssen einen Betrag zum Guthaben aufladen eingeben.', 'missing booking amount', 400 );
    }

    $inputguthabenAufladenBetrag = $request->input('guthabenAufladenBetrag');
    $amountCent = (int) getAmountCentForBetragInput($inputguthabenAufladenBetrag);

    if(!_isValidAmountCent($amountCent)) {
        return returnNewErrorObject('Ungültiger Aufladungsbetrag.', 'invalid_amount_cent', 400);
    }

    $inputCardID = trim($request->input('inputCardID'));

    $addVoucherResponse = _addVoucher($request, $inputCardID, $amountCent);

    if(isError($addVoucherResponse)) {
        return returnErrorObject($addVoucherResponse);
    }

    return response()->json($addVoucherResponse, 200);

})->middleware(['AuthenticateWithSession', 'AuthenticateIsPartnerAdminOrUser']);


function getAmountCentForBetragInput($inputBetrag) {
    $amountCent = NULL;
    if(!contains(',', $inputBetrag) && !contains('.', $inputBetrag)) {
        $amountCent = ((int) $inputBetrag) * 100;
    } else {
        if(contains(',', $inputBetrag) && !contains('.', $inputBetrag)) {
            $explodedBetrag = explode(',', $inputBetrag);
            $euroBetrag = (int) $explodedBetrag[0];
            $centBetrag = (int) $explodedBetrag[1];
            $amountCent = $euroBetrag * 100 + $centBetrag;
        }
        if(contains(',', $inputBetrag) && contains('.', $inputBetrag)) {
            $explodedTausenderBetrag = explode('.', $inputBetrag);
            $tausenderBetrag = (int) $explodedTausenderBetrag[0];
            $explodedEuroBetrag = explode(',', $explodedTausenderBetrag[1]);
            $euroBetrag = (int) $explodedEuroBetrag[0];
            $centBetrag = (int) $explodedEuroBetrag[1];
            $amountCent = $tausenderBetrag * 1000 * 100 + $euroBetrag * 100 + $centBetrag;
        }
    }

    return $amountCent;
}


Route::post('/generate-sepa-pdf', function (Request $request) {

    if(!$request->input('sepaMandateReferenceNumber')) {
        return returnNewErrorObject('Es wurde keine SEPA Mandatsreferenznummer angegeben!', 'no_sepa_reference_number', 400);
    }
    if(!$request->input('sepaCompanyCity')) {
        return returnNewErrorObject('Es wurde keine SEPA Stadt angegeben!', 'no_sepa_city', 400);
    }
    if(!$request->input('sepaCompanyStreet')) {
        return returnNewErrorObject('Es wurde keine SEPA Straße angegeben!', 'no_sepa_street', 400);
    }
    if(!$request->input('sepaCompanyZip')) {
        return returnNewErrorObject('Es wurde keine SEPA Postleitzahl angegeben!', 'no_sepa_zip', 400);
    }
    if(!$request->input('sepaAccountHolder')) {
        return returnNewErrorObject('Es wurde kein SEPA Name des Kontoinhabers angegeben!', 'no_sepa_name', 400);
    }
    if(!$request->input('sepaIBAN')) {
        return returnNewErrorObject('Es wurde keine SEPA IBAN angegeben!', 'no_sepa_iban', 400);
    }
    if(!$request->input('sepaBIC')) {
        return returnNewErrorObject('Es wurde keine SEPA BIC angegeben!', 'no_sepa_bic', 400);
    }
    if(!$request->input('sepaBankName')) {
        return returnNewErrorObject('Es wurde kein SEPA Name der Bank angegeben!', 'no_sepa_bank_name', 400);
    }

    $pdf = Pdf::loadView('sepamandat', $request->input())->setOption('defaultFont', 'Helvetica');
    return $pdf->download('sepa-mandat.pdf');

})->middleware(['AuthenticateWithSession']);


Route::post('/generate-receipt', function (Request $request) {

    $receiptPDF = handleGenerateReceipt($request);
    if(isError($receiptPDF)) {
        return returnErrorObject($receiptPDF);
    }

    return $receiptPDF->download('beleg.pdf');

})->middleware(['AuthenticateWithSession']);


function handleGenerateReceipt($request) {

    if(!$request->has('receiptType') || empty($request->input('receiptType'))) {
        return createErrorObject('Es wurde keine Belegart (receiptType) angegeben!', 'no_receiptType', 400);
    } else {
        $receiptTypeLowercased = strtolower($request->input('receiptType'));
        if($receiptTypeLowercased !== 'merchant' && $receiptTypeLowercased !== 'customer') {
            return createErrorObject('Es wurde eine ungültige Belegart (receiptType) angegeben!', 'invalid_receiptType', 400);
        }
    }

    if(!$request->has('bookingType') || empty($request->input('bookingType'))) {
        return createErrorObject('Es wurde keine Buchungsart (bookingType) angegeben!', 'no_bookingType', 400);
    } else {
        $bookingTypeLowercased = strtolower($request->input('bookingType'));
        if($bookingTypeLowercased !== 'redeemvoucher' && $bookingTypeLowercased !== 'addvoucher' && $bookingTypeLowercased !== 'addbonus') {
            return createErrorObject('Es wurde eine ungültige Buchungsart (bookingType) angegeben!', 'invalid_no_bookingType', 400);
        }
    }

    $receiptData = [];

    $company_data = getGwPersonalDataByGGUID($request->input('company_gguid'));
    if(!property_exists($company_data, 'GGUID')) {
        return createErrorObject('Es ist ein Fehler aufgetreten. Die Firma wurde nicht gefunden. Bitte kontaktieren Sie den Support.', 'unknown_error', 400);
    }

    $receiptData['companyName'] = property_exists($company_data, 'COMPNAME2') && !empty($company_data->COMPNAME2) ? $company_data->COMPNAME2 : $company_data->COMPNAME;
    $receiptData['street'] = property_exists($company_data, 'STREET2') && !empty($company_data->STREET2) ? $company_data->STREET2 : '';
    $receiptData['zip'] = property_exists($company_data, 'ZIP2') && !empty($company_data->ZIP2) ? $company_data->ZIP2 : '';
    $receiptData['city'] = property_exists($company_data, 'TOWN2') && !empty($company_data->ZIP2) ? $company_data->TOWN2 : '';
    $receiptData['phone'] = property_exists($company_data, 'TMPHONEVEROEFFENTLICHUNG') && !empty($company_data->TMPHONEVEROEFFENTLICHUNG) ? 'Tel. ' . $company_data->TMPHONEVEROEFFENTLICHUNG : '';
    $receiptData['email'] = property_exists($company_data, 'TMMAILVEROEFFENTLICHUNG') && !empty($company_data->TMMAILVEROEFFENTLICHUNG) ? $company_data->TMMAILVEROEFFENTLICHUNG : '';

    $inputCardID = $request->input('cardID');
    $cardID = substr($inputCardID, 0, 4);
    $cardID .= 'xxxxxxxx';
    $cardID .= substr($inputCardID, -3);
    $receiptData['cardID'] = $cardID;
    $receiptData['amount'] = $request->input('amount');

    $dateNow = new DateTime('now');
    $dateNow->setTimezone(new DateTimeZone('Europe/Berlin'));
    $nowDate = $dateNow->format('d.m.Y');
    $nowTime = $dateNow->format('H:i:s');

    $receiptData['nowDate'] = $nowDate;
    $receiptData['nowTime'] = $nowTime;

    $company_id = $request->input('company_id');
    $receiptData['terminalID'] = 'W' . $company_id;
    $receiptData['cardName'] = $request->input('card_name');

    $receiptData['isDemoBooking'] = _isInterest($request);

    if($receiptTypeLowercased == 'merchant') {
        $receiptData['receiptType'] = 'Händlerbeleg';
        $receiptData['descriptionText2'] = 'Die Abrechnung erfolgt mit dem nächsten Clearing.';
        if($bookingTypeLowercased ==='redeemvoucher') {
            $receiptData['bookingType'] = 'Einlösung';
            $receiptData['descriptionText1'] = 'Sie haben ' . $request->input('amount') . ' EURO erhalten.';
        } else if($bookingTypeLowercased == 'addvoucher') {
            $receiptData['bookingType'] = 'Aufladung';
            $receiptData['descriptionText1'] = 'Sie haben ' . $request->input('amount') . ' EURO Guthaben aufgeladen.';
        } else if($bookingTypeLowercased == 'addbonus') {
            $receiptData['bookingType'] = 'Einkauf/Bonus';
            $receiptData['descriptionText1'] = 'Sie haben einen Einkauf von ' . $request->input('amount') . ' EURO bonifiziert.';
        } else {
            return createErrorObject('Der Beleg konnte nicht generiert werden. Es wurde keine Buchungsart angegeben. Bitte kontaktieren Sie den Support.', 'no_bookingType', 400 );
        }
    } else if($receiptTypeLowercased == 'customer') {

        $balance = getBalanceAmountForCardID($inputCardID);
        if(isError($balance)) {
            return createErrorObject($balance['errorMessage'], 'error_checking_balance', 500);
        }

        $balanceAmount = $balance['balanceFormattedDE'];
        $receiptData['receiptType'] = 'Kundenbeleg';
        if($bookingTypeLowercased ==='redeemvoucher') {
            $receiptData['bookingType'] = 'Einlösung';
            $receiptData['descriptionText1'] = 'Vielen Dank für Ihren Einkauf!';
            $receiptData['descriptionText2'] = 'Ihr neues Guthaben beträgt ' . $balanceAmount . ' EURO';
        } else if($bookingTypeLowercased == 'addvoucher') {
            $receiptData['bookingType'] = 'Aufladung';
            $receiptData['descriptionText1'] = 'Vielen Dank für Ihre Guthabenaufladung!';
            $receiptData['descriptionText2'] = 'Ihr neues Guthaben beträgt ' . $balanceAmount . ' EURO';
        } else if($bookingTypeLowercased == 'addbonus') {
            $receiptData['bookingType'] = 'Einkauf/Bonus';
            $receiptData['descriptionText1'] = 'Vielen Dank für Ihren Einkauf!';
            $receiptData['descriptionText2'] = 'Ihr neues Guthaben beträgt ' . $balanceAmount . ' EURO';
        } else {
            return createErrorObject('Der Beleg konnte nicht generiert werden. Es wurde keine Buchungsart angegeben. Bitte kontaktieren Sie den Support.', 'no_bookingType', 400 );
        }
    }

    $receiptPaperSize = array(0,0,164.40,841.89);
    $pdf = Pdf::loadView('receipt', $receiptData)->setOption('defaultFont', 'Helvetica');
    $pdf->setPaper($receiptPaperSize);
    return $pdf;
}


function generateContractNumber($contract_number_prefix) {
    $contractNumberIsNotInUse = true;
    $contractNumber = NULL;

    while($contractNumberIsNotInUse) {
        $randomContractNumber = str_pad(mt_rand(1,99999999), 8, '0', STR_PAD_LEFT);

        $contractNumber = $contract_number_prefix . '-' . $randomContractNumber;

        $checkIfContractNumberAlreadyExists = Http::withHeaders([
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
            'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
        ])->post(env('GW_API_BASE') . '/query', [
            'query' => 'SELECT NCINTERNEID, TMVERTRAGID FROM address WHERE NCINTERNEID="' . $contractNumber . '" OR TMVERTRAGID="' . $contractNumber . '"'
        ]);

        if($checkIfContractNumberAlreadyExists->failed()) {
            return createErrorObject('Es ist ein Fehler aufgetreten. Es konnte nicht geprüft werden, ob der Account bereits registriert wurde. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
        }

        $contractNumberFromGW = json_decode($checkIfContractNumberAlreadyExists);
        if(!$contractNumberFromGW || count($contractNumberFromGW) == 0) {
            $contractNumberIsNotInUse = false;
        }
    }

    return $contractNumber;
}

function getPartnerRolle($user_role) {
    if(strtolower($user_role) == 'admin') {
        return PartnerUserRoles::ADMIN->value;
    } else if(strtolower($user_role) == 'user') {
        return PartnerUserRoles::USER->value;
    } else if(strtolower($user_role) == 'buchhaltung') {
        return PartnerUserRoles::BUCHHALTUNG->value;
    } else {
        return NULL;
    }
}



Route::get('/clear-all-sessions', function (Request $request) {
    if($request->has('h')) {
        if($request->input('h') == '6bdffe8004a11dcf578ca3c185ffe60d9c73536b') {
                DB::table('mycitycards_sessions')
                  ->where('retain', FALSE)
                  ->delete();
        }
    }
    return response()->json( new stdClass(), 200 );
});


Route::post('/generate-qrcode-links-csv', function (Request $request) {

    if(!$request->hasFile('cardsListFile')) {
        return returnNewErrorObject('Fehler beim Hochladen der Datei.', 'file_upload_error', 500);
    }

    if(!$request->file('cardsListFile')->isValid()) {
        return returnNewErrorObject('Fehler beim Hochladen der Datei. Die Datei ist ungültig.', 'file_upload_invalid_error', 500);
    }

    $cardsListFile = $request->file('cardsListFile');
    $fileContents = file($cardsListFile->getPathname());

    $secret = config('constants.secrets.balance_qr_code_url_list');
    $iv = config('constants.ivs.balance_qr_code_url_list');

    $handle = fopen(storage_path('app/temp-files/csv-generated.csv'), 'w');

    foreach ($fileContents as $line) {
        $row = str_getcsv($line);
        if(empty($row)) {
            continue;
        }

        $row[0] = trim($row[0], "\xEF\xBB\xBF");

        $textToEncrypt = trim($row[0]);

        $encrypted = openssl_encrypt($textToEncrypt, 'aes-256-cbc', $secret, 0, $iv);
        $encryptedAsHex = bin2hex($encrypted);
        $row[1] = 'https://mycity.cards/abfrage?x=' . $encryptedAsHex;
        $row[2] = $encryptedAsHex;

        fputcsv($handle, [$row[0], $row[1], $row[2]]);
    }

    fclose($handle);
    return response()->download(storage_path('app/temp-files/csv-generated.csv'), 'generated-cards-list.csv', ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename=generated-cards-list.csv'])->deleteFileAfterSend(true);
});

Route::post('/generate-potential-partners-registration-links-csv', function (Request $request) {

        if (!$request->hasFile('potentialPartnersListFile')) {
                return returnNewErrorObject('Fehler beim Hochladen der Datei.', 'file_upload_error', 500);
        }

        if (!$request->file('potentialPartnersListFile')->isValid()) {
                return returnNewErrorObject('Fehler beim Hochladen der Datei. Die Datei ist ungültig.', 'file_upload_invalid_error', 500);
        }

        $cardsListFile = $request->file('potentialPartnersListFile');
        $fileContents  = file($cardsListFile->getPathname());

        $secret = config('constants.secrets.potential_partners_registration_url_list');
        $iv     = config('constants.ivs.potential_partners_registration_url_list');

        $handle = fopen(storage_path('app/temp-files/csv-potential-partners-generated.csv'), 'w');

        foreach ($fileContents as $line) {
                $line = trim($line);
                if ($line === '') {
                        continue;
                }

                $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);

                $delimiter = (substr_count($line, ';') > substr_count($line, ',')) ? ';' : ',';

                $row = str_getcsv($line, $delimiter);
                if (empty($row)) {
                        continue;
                }

                if (!isset($row[0], $row[1], $row[2])) {
                        continue;
                }

                $row[0] = trim($row[0], "\xEF\xBB\xBF");
                $region = trim((string)$row[1]);
                $typ    = trim((string)$row[2]);

                $textToEncrypt = trim($row[0]);

                $encrypted      = openssl_encrypt($textToEncrypt, 'aes-256-cbc', $secret, 0, $iv);
                $encryptedAsHex = bin2hex($encrypted);

                $typLower   = mb_strtolower($typ);
                $isCustomer = ($typLower === 'kunde');

                $baseUrl = $isCustomer
                    ? 'https://mycity.cards/registrierung'
                    : 'https://mycity.cards/interessent-registrierung';

                $generatedUrl = $baseUrl . '?x=' . $encryptedAsHex;
                if ($region !== '') {
                        $generatedUrl .= '&region=' . rawurlencode($region);
                }

                $row[1] = $generatedUrl;
                $row[2] = $encryptedAsHex;

                fputcsv($handle, [$row[0], $row[1], $row[2]]);
        }

        fclose($handle);

        return response()
            ->download(storage_path('app/temp-files/csv-potential-partners-generated.csv'), 'generated-potential-partners-list.csv',
                ['Content-Type'        => 'text/csv', 'Content-Disposition' => 'attachment; filename=generated-potential-partners-list.csv',])
            ->deleteFileAfterSend(TRUE);
});


/*
Route::get('/generate-link', function (Request $request) {
    $secret = 's5v8y/B?E!H+KbPeShVmYq3!6w9z$C&F';
    $iv = 'r4!u7x!AD*G-KN!d';

    if($request->has('k') && $request->input('k') != '') {
        $encrypted = openssl_encrypt($request->input('k'), 'aes-256-cbc', $secret, 0, $iv);
        $encryptedAsHex = bin2hex($encrypted);
        return 'https://mycity.cards/abfrage?x=' . $encryptedAsHex;
    } else {
        return response()->json( '', 200 );
    }

    return response()->json( new stdClass(), 200 );
});
*/

Route::get('/abfrage', function (Request $request) {

    $secret = config('constants.secrets.balance_qr_code_url_list');
    $iv = config('constants.ivs.balance_qr_code_url_list');

    if($request->has('x') && $request->input('x') != '') {
        try {
            $decodedString = hex2bin($request->input('x'));
        } catch(Exception $e) {
            return response()->json( new stdClass(), 400 );
        }

        if($decodedString == false) {
            return response()->json( new stdClass(), 400 );
        }
        $decryptedCardID = openssl_decrypt($decodedString, 'aes-256-cbc', $secret, 0, $iv);
        $responseToSend = new stdClass();
        $responseToSend->cardID = $decryptedCardID;
        $responseToSend->balance = getBalanceAmountForCardID($decryptedCardID);


        $cardData = getCardForCardID($decryptedCardID);

        if(!isError($cardData) && property_exists($cardData, 'GGUID')) {
            if(property_exists($cardData, 'KVWORTDERANMELDUNG') && !empty($cardData->KVWORTDERANMELDUNG) && property_exists($cardData, 'KVWREGION') && !empty($cardData->KVWREGION)) {
                $responseToSend->region_name = $cardData->KVWREGION;
                $responseToSend->card_name = $cardData->KVWORTDERANMELDUNG;
            }

            if(property_exists($cardData, 'KVWKARTEREGISTRIERT') && $cardData->KVWKARTEREGISTRIERT === true) {
                $responseToSend->isCardRegistered = true;
            } else {
                $responseToSend->isCardRegistered = false;
            }
        }

        return response()->json( $responseToSend, 200 );
    }
    return response()->json( new stdClass(), 400 );
});

function isValidCardIDSyntax($cardID) {
    if($cardID == null || $cardID == '' || empty($cardID)) {
        return false;
    }
    if(!is_numeric($cardID) || !str_starts_with($cardID, '1761') || strlen($cardID) != 15) {
        return false;
    }

    return true;
}


function getLongDisagioText($disagio) {
    if(str_starts_with($disagio, '0.5%')) {
        return '0.5% - zzgl. 30.00 € monatlich';
    } else if(str_starts_with($disagio, '1%')) {
        return '1% - zzgl. 15.00 € monatlich';
    } else if(str_starts_with($disagio, '2% - St')) {
        return '2% - Standardmodell';
    } else if(str_starts_with($disagio, '3%')) {
        return '3% - keine Mindest-Tx-Gebühr';
    } else if(str_starts_with($disagio, '5%')) {
        return '5% - keine Teilnahmegebühr';
    } else if(str_starts_with($disagio, '0%')) {
        return '0% - Sondertarif';
    } else if(str_starts_with($disagio, '2% - keine')) {
        return '2% - keine Teilnahmegebühr';
    } else if(str_starts_with($disagio, '2%')) {
        return '2% - Standardmodell';
    } else {
        return $disagio;
    }
}

/**
 * checks if string contains string
 *
 * @param  string $needle string to search
 * @param  string $haystack string in which should be searched
 * @return boolean
 */
function contains($needle, $haystack){
    return strpos($haystack, $needle) !== false;
}

function getShortCommunityString($community) {
    $pieces = explode(" | ", $community );
    $lastPiece = end($pieces);
    $pieces2 = explode(" - ", $lastPiece);
    return end($pieces2);
}

/**
 * Checks if ObjectOrArray contains errorMessage Property so if it is a error
 *
 * @param  mixed $objectOrArray
 * @return boolean if objectOrArray is error or not
 */
function isError($objectOrArray) {

    if(is_array($objectOrArray)) {
        if(array_key_exists('errorMessage', $objectOrArray) && !empty($objectOrArray['errorMessage'])) {
            return true;
        } else {
            return false;
        }
    }

    if(is_object($objectOrArray)) {
        if(property_exists($objectOrArray, 'errorMessage') && !empty($objectOrArray->errorMessage)) {
            return true;
        } else {
            return false;
        }
    }

    return false;
}

/**
 * Creates an error object.
 *
 * @param {string} The error message.
 * @param {string} The error status code.
 * @param {string} The HTTP status code.
 * @return {object} The error object.
 *
 */
function createErrorObject($errorMessage, $errorStatusCode, $httpStatusCode = 500) {

    $errorObject = new stdClass();

    if($errorMessage != NULL && !empty($errorMessage)) {
        $errorObject->errorMessage = $errorMessage;
    } else {
        $errorObject->errorMessage = 'Es ist ein unbekannter Fehler aufgetreten';
    }

    if($errorStatusCode != NULL && !empty($errorStatusCode)) {
        $errorObject->errorStatusCode = $errorStatusCode;
    } else {
        $errorObject->errorStatusCode = 'unknown_error';
    }

    $errorObject->httpStatusCode = $httpStatusCode;

    return $errorObject;
}


/**
 * returnNewErrorObject
 *
 * @param  string $errorMessage
 * @param  string $errorStatusCode
 * @param  int $httpStatusCode
 */
function returnNewErrorObject($errorMessage, $errorStatusCode, $httpStatusCode = 500) {

    $errorObject = createErrorObject($errorMessage, $errorStatusCode, $httpStatusCode);

    return response()->json( $errorObject, $errorObject->httpStatusCode);
}


function returnErrorObject($error) {

    if(empty($error)) {
        Log::error('returnErrorObject: error object is empty');
        return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
    }

    if(is_array($error)) {
        $error = (object) $error;
    }

    if(!property_exists($error, 'httpStatusCode') || empty($error->httpStatusCode)) {
        $error->httpStatusCode = 500;
    }

    return response()->json( $error, $error->httpStatusCode );
}

function _guessSalutationFromGW($firstName, $lastName, $gender, $title, $preferredLanguage = 'de') {
    $GWGENDER = '';
    $GWTITLE = $title;
    switch ($gender) {
        case 'männlich':
            $GWGENDER = 'MALE';
            break;
        case 'weiblich':
            $GWGENDER = 'FEMALE';
            break;
        case 'divers':
            $GWGENDER = 'BOTH';
            break;
        case 'sonstige':
            $GWGENDER = 'UNKNOWN';
            break;
        default:
            break;
    }
    switch ($title) {
        case 'Prof.':
            $GWTITLE = 'PROF.';
            break;
        case 'Prof':
            $GWTITLE = 'PROF.';
            break;
        case 'Dr.':
            $GWTITLE = 'DR.';
            break;
        case 'Dr':
            $GWTITLE = 'DR.';
            break;
        default:
            break;
    }
    $gwResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/type/address/salutation', [
        'name' =>  $lastName,
        'christianName' => $firstName,
        'preferredLanguage' => $preferredLanguage,
        'term' => "",
        'guessFromTermOnly' => false,
        'title' => $GWTITLE,
        'letter' => '',
        'gender' => $GWGENDER
    ]);

    if($gwResponse->failed()) {
        Log::error("Fehler beim Abrufen von _guessSalutationFromGW für Vorname: " . $firstName . " und Nachanme: " . $lastName . " und Geschlecht: " . $gender . ": " . print_r($gwResponse->body(), true));
        $fallback_return_object = new stdClass();
        $fallback_return_object->addressterm = _getAddressterm($gender, $preferredLanguage);
        $fallback_return_object->addressletter = _getAddressletter($gender, $preferredLanguage);
        return $fallback_return_object;
    }

    $fields = json_decode($gwResponse);
    if($fields == NULL || !property_exists($fields, 'addressTerm') || !property_exists($fields, 'addressLetter')) {
        $fallback_return_object = new stdClass();
        $fallback_return_object->addressterm = _getAddressterm($gender, $preferredLanguage);
        $fallback_return_object->addressletter = _getAddressletter($gender, $preferredLanguage);
        return $fallback_return_object;
    }

    $return_object = new stdClass();
    $return_object->addressterm = $fields->addressTerm;
    $return_object->addressletter = $fields->addressLetter;
    return $return_object;
}

function _getAddressterm($gender, $country) {
    if($gender == NULL || empty($gender)) {
        return '';
    }
    $lowercasedGender = strtolower($gender);
    if($country == 'FR' || $country == 'Frankreich') {
        if($lowercasedGender === 'männlich') {
            return 'Monsieur';
        } else if ($lowercasedGender === 'weiblich') {
            return 'Madame';
        } else if($lowercasedGender === 'divers') {
            return '';
        } else {
            return '';
        }
    } else {
        if($lowercasedGender === 'männlich') {
            return 'Herrn';
        } else if ($lowercasedGender === 'weiblich') {
            return 'Frau';
        } else if($lowercasedGender === 'divers') {
            return '';
        } else {
            return '';
        }
    }
}

function _getAddressletter($gender, $country, $firstName = '', $lastName = '') {
    if($gender == NULL) {
        return '';
    }
    $lowercasedGender = strtolower($gender);
    if($country == 'FR' || $country == 'Frankreich') {
        if($lowercasedGender === 'männlich') {
            return 'Cher Monsieur';
        } else if ($lowercasedGender === 'weiblich') {
            return 'Chère Madam';
        } else if($lowercasedGender === 'divers') {
            return '';
        } else {
            return 'Madame, Monsieur';
        }
    } else {
        if($lowercasedGender === 'männlich') {
            return 'Lieber Herr';
        } else if ($lowercasedGender === 'weiblich') {
            return 'Liebe Frau';
        } else if($lowercasedGender === 'divers') {
            return 'Liebe*r ' . $firstName . '' . $lastName;
        } else {
            return 'Sehr geehrte Damen und Herren';
        }
    }
}


function gWDateToGermanDateAndTime($gwDate) {
    $date = new DateTime($gwDate, new DateTimeZone('UTC'));
    $date->setTimezone(new DateTimeZone('Europe/Berlin'));
    return $date->format('d.m.Y H:i:s');
}

function gWDateToGermanDate($gwDate) {
    $date = new DateTime($gwDate, new DateTimeZone('UTC'));
    $date->setTimezone(new DateTimeZone('Europe/Berlin'));
    return $date->format('d.m.Y');
}

function gWDateToGermanTime($gwDate) {
    $date = new DateTime($gwDate, new DateTimeZone('UTC'));
    //$date->setTimezone(new DateTimeZone('Europe/Berlin'));
    return $date->format('H:i');
}

function htmlDateToGwDate($htmlDate) {
    $date = new DateTime($htmlDate, new DateTimeZone('UTC'));
    $date->setTimezone(new DateTimeZone('Europe/Berlin'));
    return $date->format('Y-m-d\TH:i:s.v\Z');
}

function dateTimeToGWDateTime($date) {
    return $date->format('Y-m-d\TH:i:s.v\Z');
}

function gWDateToHtmlDate($gwDate) {
    $date = new DateTime($gwDate, new DateTimeZone('UTC'));
    $date->setTimezone(new DateTimeZone('Europe/Berlin'));
    return $date->format('Y-m-d');
}

function convertISODateToGermanDateAndTime($isoDate) {
    $date = new DateTime($isoDate, new DateTimeZone('UTC'));
    $date->setTimezone(new DateTimeZone('Europe/Berlin'));
    return $date->format('d.m.Y H:i:s');
}

function convertISODateToGermanDate($isoDate) {
    $date = new DateTime($isoDate, new DateTimeZone('UTC'));
    $date->setTimezone(new DateTimeZone('Europe/Berlin'));
    return $date->format('d.m.Y');
}

function convertDateWithFormatToISODate($inputDate, $inputDateFormat) {
    $d = DateTime::createFromFormat($inputDateFormat, $inputDate);
    if($d == NULL || is_bool($d)) {
        Log::error('In convertDateWithFormatToISODate() lieferte DateTime::createFromFormat() für Datum ' . print_r($inputDate, true) . ' und inputDateFormat: ' . $inputDateFormat . ' ein Fehler');
        return '';
    }
    $d->setTimezone(new DateTimeZone('Europe/Berlin'));
    return $d->format('Y-m-d\TH:i:s');
}

function convertDateWithFormatToISODateWithoutTime($inputDate, $inputDateFormat) {
    $d = DateTime::createFromFormat($inputDateFormat, $inputDate);
    if($d == NULL || is_bool($d)) {
        Log::error('In convertDateWithFormatToISODateWithoutTime() lieferte DateTime::createFromFormat() für Datum ' . print_r($inputDate, true) . ' und inputDateFormat: ' . $inputDateFormat . ' ein Fehler');
        return '';
    }
    $d->setTimezone(new DateTimeZone('Europe/Berlin'));
    return $d->format('Y-m-d');
}

function validateDate($date, $format = 'd.m.Y H:i:s') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}

function validateDateIsISOFormat($date) {
    return validateDate($date, 'Y-m-d\TH:i:s');
}

function validateDateIsISOFormatWithoutTime($date) {
    return validateDate($date, 'Y-m-d');
}

function validateDateIsUnixEpoch($date) {
    return validateDate($date, 'U');
}

function generateRandomPassword() {

    $randomString = '';

    $numbers = '0123456789';
    $numbersLength = strlen($numbers);
    for ($i = 0; $i < 1; $i++) {
        $randomString .= $numbers[rand(0, $numbersLength - 1)];
    }

    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!$%()*,-.?@^_~';
    $charactersLength = strlen($characters);
    for ($i = 0; $i < 10; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }

    $numbers = '0123456789';
    $numbersLength = strlen($numbers);
    for ($i = 0; $i < 2; $i++) {
        $randomString .= $numbers[rand(0, $numbersLength - 1)];
    }

    $symbols = '!$%()*,-.?@^_~';
    $symbolsLength = strlen($symbols);
    for ($i = 0; $i < 2; $i++) {
        $randomString .= $symbols[rand(0, $symbolsLength - 1)];
    }

    return $randomString;
}

function generateRandomPasswordToken() {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < 35; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function encryptTripleDes($message) {
    $password = "Ht5#dadd!343";
    $salt = "dKK##2k!ds";
    $cipher = 'des-ede3-cbc';
    $AESKeyLength = 24;
    $AESIVLength = openssl_cipher_iv_length('des-ede3-cbc');

    $pbkdf2 = hash_pbkdf2("SHA1", $password, mb_convert_encoding($salt, 'UTF-16LE'), 1000, $AESKeyLength + $AESIVLength, true);

    $key = substr($pbkdf2, 0, $AESKeyLength);
    $iv =  substr($pbkdf2, $AESKeyLength, $AESIVLength);

    $enc = openssl_encrypt(mb_convert_encoding($message, 'UTF-16LE', 'UTF-8'), 'des-ede3-cbc', $key, 0, $iv);
    return $enc;
}



/* transaktionsdaten abrufen von value master


   $valueMasterResponse = Http::withHeaders([
        'provider' => 'trolleymaker',
        'password' => 'poiJJ#9q9'
    ])->post('https://valuemaster.brain-behind.com/CU_WebAPI.asmx/CU_Show_TransactionsValueMaster', [
        'CardID' =>  $cardID,
        'from' => $fromDate,
        'to' => $toDate,
        'Type' => '',
        'SystemID' => ''
    ]);


    $data = json_decode($valueMasterResponse)->d;



    if($data && $data != NULL) {
        $data = json_decode($valueMasterResponse)->d;
        if($data[0]->error && $data[0]->error != '') {
            if($data[0]->error != 'No Transactions') {
                return [ 'errorMessage' => $data[0]->error ];
            }
        }

        $valueMasterResponsePartnerData = Http::withHeaders([
            'provider' => 'trolleymaker',
            'password' => 'poiJJ#9q9'
        ])->post('https://valuemaster.brain-behind.com/CU_WebAPI.asmx/CU_Partner_List', [
            'CardID' =>  $cardID,
            'CompanyName' => "",
            'City' => "",
            'ZipCode' => "",
            'Ext_ID' => "",
            'SoundEx' => false,
        ]);

        $partnerListData = json_decode($valueMasterResponsePartnerData)->d;
        if($partnerListData && !empty($partnerListData) && count($partnerListData) > 0) {

            foreach ($data as $transaction) {
                foreach ($partnerListData as $partner) {
                    if($partner->ID == $transaction->CompanyID) {
                        $transaction->partner = $partner->CompanyName;
                    }
                }
            }
        }
*/


function _addVoucher($request, $cardID, $amountCent) {

    $cardCheck = _checkIfBookingIsAllowedForCard($cardID, $request->input('region_name'), $request->input('card_name'), true, $amountCent);

    if(is_object($cardCheck)) {
        if(property_exists($cardCheck, 'errorMessage')) {
            $error_to_send = ['errorMessage' => $cardCheck->errorMessage];
            if(property_exists($cardCheck, 'remainingAmountCentToAddVoucherThisMonth') && $cardCheck->remainingAmountCentToAddVoucherThisMonth !== null) {
                $error_to_send['remainingAmountCentToAddVoucherThisMonth'] = $cardCheck->remainingAmountCentToAddVoucherThisMonth;
            }
            if(property_exists($cardCheck, 'errorStatusCode')) {
                $error_to_send['errorStatusCode'] = $cardCheck->errorStatusCode;
            } else {
                $error_to_send['errorStatusCode'] = 'unknown_error';
            }
            if(property_exists($cardCheck, 'httpStatusCode')) {
                $error_to_send['httpStatusCode'] = $cardCheck->httpStatusCode;
            } else {
                $error_to_send['httpStatusCode'] = 400;
            }
            if(property_exists($cardCheck, 'isTestcard') && $cardCheck->isTestcard !== null) {
                $error_to_send['isTestcard'] = $cardCheck->isTestcard;
            }
            return $error_to_send;
        } else {
            if(!$cardCheck->isBookingAllowed) {
                return createErrorObject('Die Kartennummer ist nicht gültig.', 'invalid_card', 400);
            }
        }
    } else {
        return createErrorObject('Es ist ein unbekannter Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
    }


    if(!_isValidAmountCent($amountCent)) {
        return createErrorObject('Es wurde ein ungültiger Betrag angegeben.', 'invalid_amount_cent', 400);
    }

    if($amountCent > (250 * 100)) {
        return createErrorObject('Es dürfen nur maximal 250€ pro Kalendermonat aufgebucht werden!', 'max_monthly_add_voucher_reached', 400);
    }

    $terminalgroupid_gutschein = $request->input('terminalgroupid_gutschein');
    $company_id = $request->input('company_id');
    $terminal_id = 'W' . $company_id;

    $valueMasterResponse = Http::withHeaders([
        'provider' => 'trolleymaker',
        'password' => 'poiJJ#9q9'
    ])->post('https://valuemaster.brain-behind.com/CU_WebAPI.asmx/CU_Consume_Voucher', [
        'CardID' =>  $cardID,
        'TerminalID' => $terminal_id,
        'TerminalGroupID' => intval($terminalgroupid_gutschein),
        'Value' => intval($amountCent),
        'ValueType' => 'amount',
        'Currency' => 'EUR',
        'UseCase' => 'add',
        'Encryption' => encryptTripleDes('trolleymaker' . intval($amountCent))
    ]);

    if($valueMasterResponse && $valueMasterResponse != NULL) {
        Log::debug($valueMasterResponse->body());
        if($valueMasterResponse['d'] ){
            $data = json_decode($valueMasterResponse)->d;
            if($data && $data != NULL) {
                if($data->status == 'OK' && $data->message != NULL) {
                    $returnObject = new stdClass();
                    if(property_exists($cardCheck, 'remainingAmountCentToAddVoucherThisMonth') && $cardCheck->remainingAmountCentToAddVoucherThisMonth !== null) {
                        $returnObject->remainingAmountCentToAddVoucherThisMonth = $cardCheck->remainingAmountCentToAddVoucherThisMonth;
                    }
                    if(property_exists($cardCheck, 'isTestcard') && $cardCheck->isTestcard !== null) {
                        $returnObject->isTestcard = $cardCheck->isTestcard;
                    }

                } else if($data->status == 807) {
                    return createErrorObject('Die Kartennummer ist ungültig.', 'invalid_cardID', 400);
                } else {
                    Log::error('Bei folgender Buchung von Guthaben aufladen ist ein Fehler aufgetreten: ' . $valueMasterResponse->body());
                    return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
                }
            } else {
                Log::error('Bei folgender Buchung von Guthaben aufladen ist ein Fehler aufgetreten: ' . $valueMasterResponse->body());
                return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
            }
        } else {
            Log::error('Bei folgender Buchung von Guthaben aufladen ist ein Fehler aufgetreten: ' . $valueMasterResponse->body());
            return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
        }
    } else {
        Log::error('Bei folgender Buchung von Guthaben aufladen ist ein Fehler aufgetreten: ' . $valueMasterResponse->body());
        return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
    }

    return $returnObject;
}

function checkIfLotNumberExists($lotNumber) {

    $checkIfLotNumberAlreadyExists = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/query', [
        'query' => 'SELECT TMNUMMER FROM SUAITEMS WHERE GWSTYPE = "Los" AND TMNUMMER="' . $lotNumber . '"'
    ]);

    if($checkIfLotNumberAlreadyExists->failed()) {
        Log::error('Es ist ein Fehler aufgetreten. Es konnte nicht geprüft werden, ob die Losnummber bereits existiert: '
            . $checkIfLotNumberAlreadyExists->body());
        return createErrorObject('Es ist ein Fehler aufgetreten. Es konnte nicht geprüft werden, ob die Losnummer bereits existiert. Bitte wenden Sie sich an den Support.', 'unkown_error', 500);
    }

    $dataAlreadyExists = json_decode($checkIfLotNumberAlreadyExists);

    if($dataAlreadyExists && count($dataAlreadyExists) > 0) {
        return true;
    }

    return false;
}


function getTransactionsFromGWByRegionPaginated($region_card_name, $amount_of_per_page, $page_number, $date_from =
    NULL) {

    $response_array = [];

    $url = env('GW_API_BASE') . '/query';
    $url .= '?entries-per-page=' . $amount_of_per_page . '&page=' . $page_number;

    $query = 'SELECT GGUID, TADKARTENNUMMER, TADBUCHUNGSDATUM, TADBUCHUNGSARTUEBERSETZUNG, TADBUCHUNGSART, TADBETRAG, TADPARTNER, GWSTYPE, GWSSTATUS, NCREFERENZ, TMORTDERANMELDUNG, TADSTICHWORT FROM TRANSAKTIONSDATEN WHERE TMORTDERANMELDUNG="' . $region_card_name . '" AND TADBETRAG != 0';
    if(!empty($date_from)) {
        $query .= 'AND TADBUCHUNGSDATUM > "' . $date_from . '"';
    }
    $query .= 'ORDER BY TADBUCHUNGSDATUM ASC';

    $gwResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post($url, [
        'query' => $query
    ]);

    if($gwResponse->failed()) {
        Log::error('Failed to get getTransactionsFromGWByRegionPaginated: ' . print_r($gwResponse->body(), true));
        $error_obj = new stdClass();
        $error_obj->errorMessage = 'Es ist ein Fehler aufgetreten.';
        return $error_obj;
    }

    $gwTransactionsData = json_decode($gwResponse);

    if(count($gwTransactionsData) > 0) {
        $response_array = $gwTransactionsData[0]->rows;
    }

    return $response_array;
}

/*
Route::post('/generate-sua-items', function (Request $request) {

    $validator = Validator::make($request->all(), [
        'card_name' => 'required|string',
    ]);

    if($validator->fails()) {
        throw new ValidationException($validator);
    }

    $activeGames = getAllActiveGamesAndActionsForRegion(sanitize_text_field($request->input('region_name')), false);
    if(isError($activeGames)) {
        Log::error($activeGame->errorMessage);
        return returnNewErrorObject('Error beim abrufen der aktiven Spiele', 'error_active_games', 500);
    }
    if(count($activeGames) <= 0) {
        return returnNewErrorObject('Keine aktiven Spiele gefunden', 'no_active_games', 400);
    }

    $date_from = '2024-11-01T00:00:00.000Z';
    if($request->has('date_from')) {
        $date_from = $request->input('date_from');
    }


    foreach ($activeGames as $activeGame) {
        if($activeGame->GWSTYPE !== 'Spiele') {
            continue;
        }
        $transactions = getTransactionsFromGWByRegionPaginated($request->input('card_name'), $request->input('amount_per_page'), $request->input('page_number') , $request->input('date_from'));
        if(isError($transactions) || count($transactions) == 0) {
            Log::error('no transations found');
            continue;
        }
        Log::error(print_r($activeGame, true));
        $amountOfTransactions = count($transactions);
        $i = 0;
        foreach ($transactions as $transaction) {
            Log::error('transaction ' . $i . ' von ' . $amountOfTransactions . '(' . $transaction->TADBUCHUNGSDATUM .
                ', ' . $transaction->GGUID . ')');
            $i++;
            if($transaction->TADBUCHUNGSART == 'Guthaben') {
                $boughtByPartner = getLinkedPartnerForTransaction($transaction->GGUID);
                if(isError($boughtByPartner) || !property_exists($boughtByPartner[0]->fields, 'GGUID')) {
                    Log::error('Error in /generate-sua-items by getLinkedPartnerForTransaction. Linked object is error or has no gguid: ' . print_r($boughtByPartner, true));
                    //sendErrorNotificationMail('Error in /generate-sua-items by getLinkedPartnerForSuaItem. Linked
                    // object is error or has no gguid: ' . print_r($createdByPartner, true));
                    continue;
                }
                $amountCent = abs($transaction->TADBETRAG);
                $amountCent = $amountCent * 100;

                _checkAndGenerateSuaItemsForActiveGame($activeGame, $transaction->TADKARTENNUMMER, $amountCent,
                    $boughtByPartner[0]->fields->GGUID, $transaction->TADBUCHUNGSDATUM);
            }
        }
    }

    return response()->json(new stdClass(), 200);
});
*/


function _checkAndGenerateSuaItemsForActiveGame($activeGame, $cardID, $amountCent,
$partnerGguid, $transactionDateString) {

    Log::error("[generateSuaItem] ENTER game={$activeGame->GGUID} card={$cardID} partner={$partnerGguid} amountCent={$amountCent} transactionDate={$transactionDateString}");
    if(property_exists($activeGame, 'TMERWERBBARVON') && !empty($activeGame->TMERWERBBARVON) && property_exists
        ($activeGame, 'TMERWERBBARBIS') && !empty($activeGame->TMERWERBBARBIS)) {
        $nowDate = new DateTime($transactionDateString);
        $startDate = new DateTime($activeGame->TMERWERBBARVON);
        $endDate = new DateTime($activeGame->TMERWERBBARBIS);
        if($nowDate < $startDate || $nowDate > $endDate) {
            Log::error("[generateSuaItem] skip: transaction date {$transactionDateString} outside TMERWERBBARVON ({$activeGame->TMERWERBBARVON}) / TMERWERBBARBIS ({$activeGame->TMERWERBBARBIS}) for game {$activeGame->GGUID}");
            return false;
        }
    }

    if($activeGame->GWSTYPE === 'Spiele' && property_exists($activeGame, 'TMSPIELTYP')) {
        if(property_exists($activeGame, 'TMMINDESTWERT') && is_int($activeGame->TMMINDESTWERT)) {
            if($amountCent < intval($activeGame->TMMINDESTWERT)) {
                Log::error("[generateSuaItem] skip: amountCent {$amountCent} < TMMINDESTWERT {$activeGame->TMMINDESTWERT} for game {$activeGame->GGUID}");
                return false;
            }
        }

        //check if should generate bingo card for customer
        if(str_contains($activeGame->TMSPIELTYP, 'Bingo') || str_contains($activeGame->TMSPIELTYP, 'Tombola')) {
            //bingo game running
            $customerAddress = getCustomerForCardID($cardID);
            if(isError($customerAddress) || !property_exists($customerAddress, 'GGUID')) {
                Log::error("[generateSuaItem] skip: no customer found for cardID {$cardID} (game {$activeGame->GGUID}): " . print_r($customerAddress, true));
                return false;
            }

            if(property_exists($activeGame, 'TMKARTEREGISTRIERT') && $activeGame->TMKARTEREGISTRIERT === true) {
                if(!property_exists($customerAddress, 'CHRISTIANNAME') || empty($customerAddress->CHRISTIANNAME)) {
                    Log::error("[generateSuaItem] skip: customer {$customerAddress->GGUID} has no CHRISTIANNAME but game {$activeGame->GGUID} requires TMKARTEREGISTRIERT");
                    return false;
                }
            }

            $suaItemsForCustomer = getItemsForGamesAndActionsGguid($activeGame->GGUID, $customerAddress->GGUID);
            $activeSuaItemsForCustomer = [];

            foreach ($suaItemsForCustomer as $suaItem) {
                if(property_exists($suaItem, 'GWSSTATUS') && (strtolower($suaItem->GWSSTATUS) == 'lagernd' ||
                        strtolower($suaItem->GWSSTATUS) == 'ausgegeben')) {
                    $activeSuaItemsForCustomer[] = $suaItem;
                }
            }

            if(property_exists($activeGame, 'TMANZAHLMAXITEMS') && intval
                ($activeGame->TMANZAHLMAXITEMS) > 0 && count($activeSuaItemsForCustomer) >= intval
                ($activeGame->TMANZAHLMAXITEMS)) {
                Log::error("[generateSuaItem] skip: active SuA items for customer (" . count($activeSuaItemsForCustomer) . ") >= TMANZAHLMAXITEMS ({$activeGame->TMANZAHLMAXITEMS}) for game {$activeGame->GGUID} customer {$customerAddress->GGUID}");
                return false;
            }

            if(property_exists($activeGame, 'TMANZAHLPROPARTNER') && intval
                ($activeGame->TMANZAHLPROPARTNER) > 0) {

                $boughtByPartners = [];
                foreach ($activeSuaItemsForCustomer as $activeSuaItem) {
                    $createdByPartner = getLinkedPartnerForSuaItem($activeSuaItem->GGUID);
                    if(intval($activeGame->TMANZAHLPROPARTNER) == 1 && $partnerGguid === $createdByPartner->GGUID) {
                        Log::error("[generateSuaItem] skip: TMANZAHLPROPARTNER=1 and partner {$partnerGguid} already created one for game {$activeGame->GGUID} customer {$customerAddress->GGUID}");
                        return false;
                    }
                    if(!array_key_exists($createdByPartner->GGUID, $boughtByPartners)) {
                        $boughtByPartners[$createdByPartner->GGUID] = 1;
                    } else {
                        $boughtByPartners[$createdByPartner->GGUID] = $boughtByPartners[$createdByPartner->GGUID]
                            + 1;
                    }
                    if(array_key_exists($partnerGguid, $boughtByPartners) && $boughtByPartners[$partnerGguid] >=
                        intval($activeGame->TMANZAHLPROPARTNER)) {
                        Log::error("[generateSuaItem] skip: partner {$partnerGguid} reached TMANZAHLPROPARTNER ({$activeGame->TMANZAHLPROPARTNER}) for game {$activeGame->GGUID} customer {$customerAddress->GGUID}");
                        return false;
                    }
                }
            }

            //Creates the sua items and links
            $suaItem = new stdClass();
            $suaItem->GWSSTATUS = 'ausgegeben';

            if(str_contains($activeGame->TMSPIELTYP, 'Tombola')) {
                if(property_exists($activeGame, 'TMGENERIERUNGRANDOM') && $activeGame->TMGENERIERUNGRANDOM == true) {
                    //genrate random lotNumber
                    $randomMin = 100000;
                    $randomMax = 999999;
                    if(property_exists($activeGame, 'TMNUMMERNKREISANFANG') &&
                        !empty($activeGame->TMNUMMERNKREISANFANG)) {
                        $randomMin = intval($activeGame->TMNUMMERNKREISANFANG);
                    }
                    if(property_exists($activeGame, 'TMNUMMERNKREISBEGRENZT') && $activeGame->TMNUMMERNKREISBEGRENZT == true) {
                        $randomMin = intval($activeGame->TMNUMMERNKREISANFANG);
                        if(property_exists($activeGame, 'TMNUMMERNKREISENDE') &&
                            !empty($activeGame->TMNUMMERNKREISENDE)) {
                            $randomMax = intval($activeGame->TMNUMMERNKREISENDE);
                        }
                    }
                    $generatedLotNumber = 0;
                    do {
                        $generatedLotNumber = random_int($randomMin, $randomMax);
                        $lotNumberExists = checkIfLotNumberExists($generatedLotNumber);
                    } while ($lotNumberExists || isError($lotNumberExists));
                } else {
                    //generate next higher lotNumber
                    $suaItemsForGame = getItemsForGamesAndActionsGguid($activeGame->GGUID, null);
                    $activeSuaItemsForGame = [];
                    foreach ($suaItemsForGame as $tempSuaItem) {
                        if(property_exists($tempSuaItem, 'GWSSTATUS') && (strtolower($tempSuaItem->GWSSTATUS) == 'lagernd' ||
                                strtolower($tempSuaItem->GWSSTATUS) == 'ausgegeben')) {
                            $activeSuaItemsForGame[] = $tempSuaItem;
                        }
                    }

                    if(count($activeSuaItemsForGame) > 0) {
                        $highestLotNumber = 1;
                        foreach ($activeSuaItemsForGame as $activeSuaItem) {
                            if(intval($activeSuaItem->TMNUMMER) > $highestLotNumber) {
                                $highestLotNumber = intval($activeSuaItem->TMNUMMER);
                            }
                        }
                        $generatedLotNumber = $highestLotNumber + 1;
                    } else {
                        if(property_exists($activeGame, 'TMNUMMERNKREISANFANG') &&
                            !empty($activeGame->TMNUMMERNKREISANFANG)) {
                            $generatedLotNumber = intval($activeGame->TMNUMMERNKREISANFANG);
                        } else {
                            $generatedLotNumber = 1;
                        }
                    }
                }

                $suaItem->GWSTYPE = 'Los';
                $suaItem->TMNUMMER = strval($generatedLotNumber);

            } else if(str_contains($activeGame->TMSPIELTYP, 'Bingo')) {
                $maxNumber = 90;
                if(str_contains($activeGame->TMSPIELTYP, '75er Steine')) {
                    $maxNumber = 75;
                }

                $bingoNumbers = [];
                for ($i = 0; $i < 20; $i++) {
                    $generatedBingoNumber = 0;
                    do {
                        $generatedBingoNumber = random_int(1, $maxNumber);
                    } while(in_array($generatedBingoNumber, $bingoNumbers));
                    $bingoNumbers[] = $generatedBingoNumber;
                }

                $suaItem->GWSTYPE = 'Spielkarte';
                $suaItem->TMNUMMER = implode(',', $bingoNumbers);
            }

            $createdSuaItem = _createSuaItemInGw($suaItem);

            if(isError($createdSuaItem)) {
                Log::error('Error in _checkAndGenerateSuaItemsForActiveGame by _createSuaItemInGw: ' . print_r($createdSuaItem, true));
                return false;
            }
            $linkedToCustomer = addLinkSuaItemToCustomer($createdSuaItem, $customerAddress->GGUID);
            if(isError($linkedToCustomer)) {
                Log::error('Error in _checkAndGenerateSuaItemsForActiveGame by addLinkSuaItemToCustomer: ' . print_r($linkedToCustomer, true));
                return false;
            }
            $linkedToPartner = addLinkSuaItemToPartner($createdSuaItem, $partnerGguid);
            if(isError($linkedToPartner)) {
                Log::error('Error in _checkAndGenerateSuaItemsForActiveGame by addLinkSuaItemToPartner: ' . print_r($linkedToPartner, true));
                return false;
            }
            $linkedToGamesAndAction = addLinkSuaItemToGamesAndAction($createdSuaItem, $activeGame->GGUID);
            if(isError($linkedToGamesAndAction)) {
                Log::error('Error in _checkAndGenerateSuaItemsForActiveGame by addLinkSuaItemToGamesAndAction: ' . print_r($linkedToGamesAndAction, true));
                return false;
            }
            $createdGguid = is_string($createdSuaItem) ? $createdSuaItem : (is_object($createdSuaItem) && property_exists($createdSuaItem, 'GGUID') ? $createdSuaItem->GGUID : print_r($createdSuaItem, true));
            Log::error("[generateSuaItem] item created and linked: gguid={$createdGguid} for customer {$customerAddress->GGUID} / partner {$partnerGguid} / game {$activeGame->GGUID}");
        } else {
            $gameType = property_exists($activeGame, 'TMSPIELTYP') ? $activeGame->TMSPIELTYP : 'unset';
            Log::error("[generateSuaItem] skip: game {$activeGame->GGUID} TMSPIELTYP='{$gameType}' is neither Bingo nor Tombola");
        }
    } else {
        $gwsType = property_exists($activeGame, 'GWSTYPE') ? $activeGame->GWSTYPE : 'unset';
        $hasGameType = property_exists($activeGame, 'TMSPIELTYP') ? 'yes' : 'no';
        Log::error("[generateSuaItem] skip: game {$activeGame->GGUID} is not GWSTYPE=Spiele (got '{$gwsType}') or has no TMSPIELTYP (has: {$hasGameType})");
    }

    Log::error("[generateSuaItem] EXIT true (item created or no-op) game={$activeGame->GGUID} card={$cardID}");
    return true;
}

function _checkGamesAndGenerateSuAItems($region_name, $card_name, $cardID, $amountCent, $partnerGguid) {
   $activeGames = getAllActiveGamesAndActionsForRegion($region_name, false);
   if(isError($activeGames)) {
       //fail silently
       return;
   }
   if(count($activeGames) <= 0) {
       return;
   }

   foreach ($activeGames as $activeGame) {
       _checkAndGenerateSuaItemsForActiveGame($activeGame, $cardID, $amountCent, $partnerGguid);
   }
}


function _checkBalance($request) {
    if(!$request->has('cardID') || $request->input('cardID') == '' || strlen($request->input('cardID')) == 0) {
        return createErrorObject('Es wurde keine Kartennummer angegeben.', 'no_cardID', 400);
    }

    $inputCardID = trim($request->input('cardID'));

    if(!isValidCardIDSyntax($inputCardID)) {
        return createErrorObject('Die Kartennummer ist ungültig.', 'invalid_cardID', 400);
    }

    if(_isCustomer($request)) {
        if(!isContainsCardIDInCustomerSession($request, $inputCardID)) {
            Log::error('Dieser Account ist nicht berechtigt die Kartennummer ' . $inputCardID . ' abzufragen.');
            return createErrorObject('Dieser Account ist nicht berechtigt diese Kartennummer abzufragen.', 'invalid_cardID', 400);
        }
    }

    $balance = api_getBalanceAmount($inputCardID);



    $cardCheck = _checkIfBookingIsAllowedForCard($inputCardID, $request->input('region_name'), $request->input('card_name'));

    $balance['isCardRegistered'] = false;
    if(is_object($cardCheck)) {
        if(property_exists($cardCheck, 'remainingAmountCentToAddVoucherThisMonth') && $cardCheck->remainingAmountCentToAddVoucherThisMonth !== null) {
            $balance['remainingAmountCentToAddVoucherThisMonth'] = $cardCheck->remainingAmountCentToAddVoucherThisMonth;
        }
        if(property_exists($cardCheck, 'remainingAmountToAddVoucherThisMonthFormattedDE') && $cardCheck->remainingAmountToAddVoucherThisMonthFormattedDE !== null) {
            $balance['remainingAmountToAddVoucherThisMonthFormattedDE'] = $cardCheck->remainingAmountToAddVoucherThisMonthFormattedDE;
        }
        if(property_exists($cardCheck, 'remainingAmountToAddVoucherThisMonthFormattedEN') && $cardCheck->remainingAmountToAddVoucherThisMonthFormattedEN !== null) {
            $balance['remainingAmountToAddVoucherThisMonthFormattedEN'] = $cardCheck->remainingAmountToAddVoucherThisMonthFormattedEN;
        }
        if(property_exists($cardCheck, 'isTestcard') && $cardCheck->isTestcard !== null) {
            $balance['isTestcard'] = $cardCheck->isTestcard;
        }
        if(property_exists($cardCheck, 'errorMessage')) {
            unset($balance['balanceFormattedDE']);
            unset($balance['balanceFormattedEN']);
            unset($balance['balanceCent']);
            unset($balance['isCardRegistered']);
            unset($balance['isTestcard']);
            $balance['errorMessage'] = $cardCheck->errorMessage;
            $balance['errorStatusCode'] = $cardCheck->errorStatusCode;
            $balance['httpStatusCode'] = 400;
            return $balance;
        } else {
            if(!$cardCheck->isBookingAllowed) {
                unset($balance['balanceFormattedDE']);
                unset($balance['balanceFormattedEN']);
                unset($balance['balanceCent']);
                unset($balance['isCardRegistered']);
                unset($balance['isTestcard']);
                $balance['errorMessage'] = 'Die Karte ist ungültig.';
                $balance['errorStatusCode'] = 'invalid_cardID';
                $balance['httpStatusCode'] = 400;
                return $balance;
            } else {
                $balance['isCardRegistered'] = $cardCheck->isCardRegistered;
            }
        }
    } else {
        unset($balance['balanceFormattedDE']);
        unset($balance['balanceFormattedEN']);
        unset($balance['balanceCent']);
        unset($balance['isCardRegistered']);
        unset($balance['isTestcard']);
        $balance['errorMessage'] = 'Unknown Error';
        $balance['errorStatusCode'] = $cardCheck->errorStatusCode;
        $balance['httpStatusCode'] = 500;
        return $balance;
    }

    return $balance;
}


function _redeemVoucher($request, $cardID, $amountCent) {

    $cardCheck = _checkIfBookingIsAllowedForCard($cardID, $request->input('region_name'), $request->input('card_name'));

    if(is_object($cardCheck)) {
        if(property_exists($cardCheck, 'errorMessage')) {
            $error_to_send = ['errorMessage' => $cardCheck->errorMessage, 'httpStatusCode' => 400];
            if(property_exists($cardCheck, 'errorStatusCode')) {
                $error_to_send['errorStatusCode'] = $cardCheck->errorStatusCode;
            } else {
                $error_to_send['errorStatusCode'] = 'unknown_error';
            }
            return $error_to_send;
        } else {
            if(!$cardCheck->isBookingAllowed) {
                return createErrorObject('Die Kartennummer ist nicht gültig.', 'invalid_card', 400);
            }
        }
    } else {
        return createErrorObject('Es ist ein unbekannter Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
    }

    $balances = getAllBalances($cardID);

    $terminalgroupid_gutschein = $request->input('terminalgroupid_gutschein');
    $terminalgroupid_mitarbeitercard = $request->input('terminalgroupid_mitarbeitercard');
    $company_id = $request->input('company_id');
    $terminal_id = 'W' . $company_id;

    if(!_isValidAmountCent($amountCent)) {
        return createErrorObject('Es wurde ein ungültiger Betrag angegeben.', 'invalid_amount_cent', 400);
    }

    $remainingAmount = $amountCent;

    if((int)$remainingAmount < 0) {
        //if negative amount is sent, then do a addVoucher()
        $addVoucherResponse = _addVoucher($request, $cardID, abs($remainingAmount));
        if(isError($addVoucherResponse)) {
            return returnErrorObject($addVoucherResponse);
        }
        return new stdClass();
    }

    if((int)$remainingAmount > 0 && isset($balances['balanceAmount']) && $balances['balanceAmount'] < $remainingAmount) {
        return createErrorObject('Es ist nicht ausreichend Guthaben auf der Karte.', 'not_sufficient_funds', 400);
    }

    $terminalGroups = ['1212001', $terminalgroupid_gutschein, $terminalgroupid_mitarbeitercard];

    foreach ($terminalGroups as $terminalGroup) {
        if((int)$remainingAmount > 0 && isset($balances[$terminalGroup])) {
            if((int)$balances[$terminalGroup]->value == 0) {
                continue;
            }

            if((int)$balances[$terminalGroup]->value >= (int)$remainingAmount) {
                $amountToBook = (int)$remainingAmount;
                $remainingAmount = 0;
            } else {
                $amountToBook = (int)$balances[$terminalGroup]->value;
                $remainingAmount = $remainingAmount - (int)$balances[$terminalGroup]->value;
            }

            if($amountToBook > 0) {
                $valueMasterResponse = Http::withHeaders([
                    'provider' => 'trolleymaker',
                    'password' => 'poiJJ#9q9'
                ])->post('https://valuemaster.brain-behind.com/CU_WebAPI.asmx/CU_Consume_Voucher_Terminalgroup', [
                    'CardID' =>  $cardID,
                    'TerminalID' => $terminal_id,
                    'Terminalgroup' => $terminalGroup,
                    'Value' => (int)$amountToBook,
                    'ValueType' => 'amount',
                    'Currency' => 'EUR',
                    'UseCase' => 'redeem',
                    'PIN' => '',
                    'Encryption' => encryptTripleDes('trolleymaker' . $amountToBook)
                ]);

                if($valueMasterResponse && $valueMasterResponse != NULL) {
                    Log::Debug($valueMasterResponse->body());
                    if($valueMasterResponse['d'] ){
                        $data = json_decode($valueMasterResponse)->d;
                        if($data && $data != NULL) {
                            if($data->status == 'OK' && $data->message != NULL) {

                            } else if($data->status == 807) {
                                return createErrorObject('Die Kartennummer ist ungültig.', 'invalid_cardID', 400);
                            } else {
                                if($data->error == 'Not sufficient funds') {
                                    return createErrorObject('Es ist nicht ausreichend Guthaben auf der Karte.<br />Für erneute Buchung OK klicken.', 'not_sufficient_funds', 400 );
                                } else {
                                    Log::error('Bei folgender Buchung von Guthaben einlösen ist ein Fehler aufgetreten: ' . $valueMasterResponse->body());
                                    return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
                                }
                            }
                        } else {
                            Log::error('Bei folgender Buchung von Guthaben einlösen ist ein Fehler aufgetreten: ' . $valueMasterResponse->body());
                            return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
                        }
                    } else {
                        Log::error('Bei folgender Buchung von Guthaben einlösen ist ein Fehler aufgetreten: ' . $valueMasterResponse->body());
                        return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
                    }
                } else {
                    Log::error('Bei folgender Buchung von Guthaben einlösen ist ein Fehler aufgetreten: ' . $valueMasterResponse->body());
                    return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
                }
            }
        }
    }

    if($remainingAmount > 0) {
        Log::error('Bei folgender Buchung von Guthaben einlösen ist ein Fehler aufgetreten. Das Guthaben wurde gar nicht oder nicht vollständig eingelöst. (Evtl. Karte und Partner unterschiedliche TerminalgroupIDs?): Kartennummer: ' . $cardID);
        return createErrorObject('Das Guthaben wurde gar nicht oder nicht vollständig eingelöst. Bitte wenden Sie sich an den Support.', 'redeem_not_complete', 400);
    }

    _checkAndGenerateSuaItemsForActiveGameOnRedeemVoucher($cardID, $amountCent, $request->input('company_gguid'), sanitize_text_field($request->input('region_name')));

    return new stdClass();
}

function _checkAndGenerateSuaItemsForActiveGameOnRedeemVoucher($cardID, $amountCent, $partnerGguid, $region_name) {

    $activeGames = getAllActiveGamesAndActionsForRegion($region_name, false);
    if(isError($activeGames)) {
        Log::error(print_r($activeGames->errorMessage, true));
        return createErrorObject('Error beim abrufen der aktiven Spiele', 'error_active_games', 500);
    }
    if(count($activeGames) <= 0) {
        return false;
    }

    if(count($activeGames) > 0) {
        $now = _getGWNowDate();
        foreach ($activeGames as $activeGame) {
            _checkAndGenerateSuaItemsForActiveGame($activeGame, $cardID, $amountCent, $partnerGguid, $now);
        }
    }
    return true;
}

function _addBonus($request, $cardID, $amountCent) {

    $cardCheck = _checkIfBookingIsAllowedForCard($cardID, $request->input('region_name'), $request->input('card_name'));

    if(is_object($cardCheck)) {
        if(property_exists($cardCheck, 'errorMessage')) {
            $error_to_send = ['errorMessage' => $cardCheck->errorMessage, 'httpStatusCode' => 400];
            if(property_exists($cardCheck, 'errorStatusCode')) {
                $error_to_send['errorStatusCode'] = $cardCheck->errorStatusCode;
            } else {
                $error_to_send['errorStatusCode'] = 'unknown_error';
            }
            return $error_to_send;
        } else {
            if(!$cardCheck->isBookingAllowed) {
                return createErrorObject('Die Kartennummer ist nicht gültig.', 'invalid_card', 400);
            }
        }
    } else {
        return createErrorObject('Es ist ein unbekannter Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 400);
    }


    if(!_isValidAmountCent($amountCent)) {
        return createErrorObject('Es wurde ein ungültiger Betrag angegeben.', 'invalid_amount_cent', 400);
    }

    //$deFormattedAmount = number_format(($amountCent / 100), 2, ',', '.');

    $now = _getVMNowDate();

    $company_id = $request->input('company_id');

    $valueMasterResponse = Http::withHeaders([
        'provider' => 'trolleymaker',
        'password' => 'poiJJ#9q9'
    ])->post('https://valuemaster.brain-behind.com/CU_WebAPI.asmx/CU_Add_Purchase', [
        'CardID' =>  $cardID,
        'TerminalID' => 'W' . $company_id,
        'CompanyID' => $company_id,
        'PurchaseAmount' => $amountCent,
        'PurchaseDate' => $now,
        'EntryLine' => 'Bonus aufgebucht.',
        'Encryption' => encryptTripleDes('trolleymaker' . $amountCent)
    ]);



    if($valueMasterResponse && $valueMasterResponse != NULL) {
        Log::debug($valueMasterResponse->body());
        if($valueMasterResponse['d']) {
            $data = json_decode($valueMasterResponse)->d;
            if($data && $data != NULL) {
                if($data->status == 'OK' && $data->message != NULL) {

                } else if($data->status == 807) {
                    return createErrorObject('Die Kartennummer ist ungültig.', 'invalid_cardID', 400);
                } else {
                    Log::error('Bei folgender Buchung von Add Bonus ist ein Fehler aufgetreten: ' . $valueMasterResponse->body());
                    return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
                }
            } else {
                Log::error('Bei folgender Buchung von Add Bonus ist ein Fehler aufgetreten: ' . $valueMasterResponse->body());
                return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
            }
        } else {
            Log::error('Bei folgender Buchung von Add Bonus ist ein Fehler aufgetreten: ' . $valueMasterResponse->body());
            return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
        }
    } else {
        Log::error('Bei folgender Buchung von Add Bonus ist ein Fehler aufgetreten: ' . $valueMasterResponse->body());
        return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
    }

    return new stdClass();
}


function _getFAQs($request, $faqType) {

    if(!$request->has('region_name') || $request->input('region_name') == NULL || $request->input('region_name') == '') {
        Log::error('[API]: /customers/faqs: Es wurde keine region_name in der Session gefunden.');
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'no_region_name', 400 );
    }

    if(!$request->has('card_name') || $request->input('card_name') == NULL || $request->input('card_name') == '') {
        Log::error('[API]: /customers/faqs: Es wurde keine card_name in der Session gefunden.');
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'no_card_name', 400 );
    }

    if(!$faqType || $faqType == NULL || $faqType == '') {
        return createErrorObject('Es wurde kein FAQ Typ angegeben!', 'no_faqType', 400);
    }

    $faqs = [];
    $faqsResponse = getRegionData($request->input('region_name'), $request->input('card_name'), ['acf.' . $faqType]);
    if(!(!isError($faqsResponse) && property_exists($faqsResponse, 'acf') && property_exists($faqsResponse->acf, $faqType))) {
        Log::error('Es ist ein Fehler aufgetreten, die Kunden FAQs konnten nicht abgerufen werden');
        return $faqsResponse;
    } else {
        $faqs = $faqsResponse->acf->{$faqType};
    }

    $responseArray = [];
    foreach($faqs as $currentFaq) {
        $temp = new stdClass();

        $temp->id = property_exists($currentFaq, 'ID') ? $currentFaq->ID : -1;

        if(property_exists($currentFaq, 'post_date') && !empty($currentFaq->post_date)) {
            $temp->date = convertDateWithFormatToISODate($currentFaq->post_date, "Y-m-d H:i:s");
        } else {
            $temp->date = '';
        }

        if(property_exists($currentFaq, 'post_modified') && !empty($currentFaq->post_modified)) {
            $temp->modified = convertDateWithFormatToISODate($currentFaq->post_modified, "Y-m-d H:i:s");
        } else {
            $temp->modified = '';
        }

        $temp->status = property_exists($currentFaq, 'post_status') ? $currentFaq->post_status : "";
        if($temp->status == "" || $temp->status != "publish") {
            continue;
        }
        $temp->slug = property_exists($currentFaq, 'post_name') ? $currentFaq->post_name : "";

        $temp->question = property_exists($currentFaq, 'question') ? $currentFaq->question : "";
        $temp->answer = property_exists($currentFaq, 'answer') ? $currentFaq->answer : "";

        array_push($responseArray, $temp);
    }

    return $responseArray;
}

Route::get('/categories', function (Request $request) {
    $categories = _getSuggestedValuesForAddress(['CATEGORY']);

    if(isError($categories)){
        return returnErrorObject($categories);
    }

    return response()->json($categories['CATEGORY'], 200);
});


function _handleDeleteUser($request) {

    if(!$request->has('alsoDeleteAllCards')) {
        Log::error('Es wurde nicht angegeben, ob auch alle Karten gelöscht werden sollen. Bitte wenden Sie sich an den Support');
        return createErrorObject('Es wurde nicht angegeben, ob auch alle Karten gelöscht werden sollen. Bitte wenden Sie sich an den Support.', 'no_alsoDeleteAllCards', 400);
    }

    if(!$request->has('contact_person_gguid') || empty($request->input('contact_person_gguid'))) {
        Log::error('Bei Delete User hat der Requests keine contact_person_gguid');
        return createErrorObject('Sie sind anscheinend nicht in Ihren Account eingeloggt. Bitte wenden Sie sich an den Support.', 'no_contact_person_gguid', 400);
    }

    $addressGGUID = $request->input('contact_person_gguid');
    $now = _getGWNowDate();

    $addressFieldsToUpdate = new stdClass();
    $addressFieldsToUpdate->NCKONTOAKTIVIERT = false;
    $addressFieldsToUpdate->GWSTYPE = 'Archiv Kunden';
    $addressFieldsToUpdate->TMLOESCHANFORDERUNG = true;
    $addressFieldsToUpdate->TMLOESCHDATUM = $now;

    if($request->input('alsoDeleteAllCards') == true) {
        //also delete the cards of this account

        $cards = getCardsForCustomer($addressGGUID);
        if(isError($cards)) {
            Log::error("no card_data for " . $addressGGUID . ": " . print_r($cards, true));
            return createErrorObject( 'Für Ihren Account wurden keine Karten gefunden. Bitte wenden Sie sich an den Support', 'no_cardIDs', 500);
        }

        $cardGGUIDs = array_column($cards, 'GGUID');

        $cardFieldsToUpdate = new stdClass();
        $cardFieldsToUpdate->KVWKARTEAKTIVVM = false;
        $cardFieldsToUpdate->GWSTYPE = 'Archiv Karten';
        $cardFieldsToUpdate->GWSSTATUS = 'deaktiviert';
        $cardFieldsToUpdate->KVWLOESCHANFORDERUNG = true;
        $cardFieldsToUpdate->KVWBELADUNGFREI = false;
        $cardFieldsToUpdate->KVWLOESCHDATUM = $now;

        foreach ($cardGGUIDs as $cardGGUID) {
            if(!updateGwCardData($cardGGUID, $cardFieldsToUpdate)) {
                return createErrorObject('Beim Löschen einer oder mehrerer Ihrer Karten ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 500 );
            }
        }

        //Mail an technik@
    }


    if(!updateGwAddressData($request->input('contact_person_gguid'), $addressFieldsToUpdate)) {
        return createErrorObject('Es ist ein Fehler aufgetreten. Ihr Account konnte nicht gelöscht werden.', 'unknown_error', 500 );
    }

    return $addressFieldsToUpdate;
}



Route::prefix('api/v1')->middleware([AuthenticateWithApiKey::class])->group(function () {

    Route::post('/check-balance', function (Request $request) {

        if(!$request->has('cardID') || $request->input('cardID') == '' || strlen($request->input('cardID')) == 0) {
            return returnNewErrorObject('Es wurde keine Kartennummer angegeben.', 'no_cardID', 400);
        }

        $checkBalanceResponse = _checkBalance($request);
        if(isError($checkBalanceResponse)) {
            return returnErrorObject($checkBalanceResponse);
        }

        $isExternalCard = api_getIsExternalCard($request->input('cardID'));
        if (isError($isExternalCard)) {
            return returnNewErrorObject('Es konnte nicht ermittelt werden, ob es sich um eine externe Karte handelt.',
            'no_cardID', 400);
        }

        $checkBalanceResponse['isExternalCard'] = $isExternalCard;
        $checkBalanceResponse['remainingDepositAmountCent'] = $checkBalanceResponse["remainingAmountCentToAddVoucherThisMonth"];
        $returnObject = (object)$checkBalanceResponse;

        return response()->json($returnObject, 200);

    })->middleware(['AuthenticateWithApi', 'AuthenticateIsPartnerAdminOrPartnerUserOrCustomer']);

    Route::post('/check-balance-multi', function (Request $request) {

        if(!$request->has('cardsIDs') || !is_array($request->input('cardsIDs')) || count($request->input('cardsIDs')) == 0) {
            return returnNewErrorObject('Es wurden keine cardsIDs angegeben oder falsch angegeben.', 'no_cardsIDs', 400);
        }

        $inputCardIDs = $request->input('cardsIDs');

        $response = [];
        foreach ($inputCardIDs as $inputCardID) {
            if(!isValidCardIDSyntax($inputCardID)) {
                return returnNewErrorObject('Eine der Kartennummern ist ungültig.', 'invalid_cardsIDs', 400);
            }

            if(_isCustomer($request)) {
                if(!isContainsCardIDInCustomerSession($request, $inputCardID)) {
                    Log::error('Dieser Account ist nicht berechtigt die Kartennummer ' . $inputCardID . ' abzufragen.');
                    return returnNewErrorObject('Dieser Account ist nicht berechtigt diese Kartennummer abzufragen.', 'invalid_cardID', 400);
                }
            }

            $balance = getBalanceAmountForCardID($inputCardID);



            $cardCheck = _checkIfBookingIsAllowedForCard($inputCardID, $request->input('NCREGION'), $request->input('NCORTDERANMELDUNG'));

            $balance['isCardRegistered'] = false;
            if(is_object($cardCheck)) {
                if(property_exists($cardCheck, 'errorMessage')) {
                    $balance['errorMessage'] = $cardCheck->errorMessage;
                    $balance['errorStatusCode'] = $cardCheck->errorStatusCode;
                    return response()->json( $balance, 500 );
                } else {
                    if(!$cardCheck->isBookingAllowed) {
                        $balance['errorMessage'] = 'Die Karte ist ungültig.';
                        $balance['errorStatusCode'] = 'invalid_cardID';
                        return response()->json( $balance, 500 );
                    } else {
                        $balance['isCardRegistered'] = $cardCheck->isCardRegistered;
                        if(property_exists($cardCheck, 'isTestcard') && $cardCheck->isTestcard !== null) {
                            $balance['isTestcard'] = $cardCheck->isTestcard;
                        }
                    }
                }
            } else {
                $balance['errorMessage'] = 'Unknown Error';
            }

            if(array_key_exists('errorMessage', $balance) && !empty($balance['errorMessage'])) {
                return response()->json( $balance, 500 );
            }

            $response[strval($inputCardID)] = $balance;
        }

        return response()->json($response, 200);

    })->middleware(['AuthenticateWithApi', 'AuthenticateIsPartnerAdminOrPartnerUserOrCustomer']);

    Route::get('/customers/transactions-balances', function (Request $request) {
        $transactions_and_balance = _getCustomersTransactionsAndBalance($request);
        if(isError($transactions_and_balance)) {
            return returnErrorObject($transactions_and_balance);
        }

        return response()->json( $transactions_and_balance, 200 );

    })->middleware(['AuthenticateWithApi', 'AuthenticateIsCustomer']);

    Route::post('/cancel-transaction', function (Request $request) {

        $validator = Validator::make($request->all(), [
            'cardID' => 'required|numeric|digits:15',
            'cancellationAmountCent' => 'required|numeric',
            'terminalgroupid_gutschein' => 'required|string'
        ]);

        if($validator->fails()) {
            throw new ValidationException($validator);
        }

        /** Card $card **/
        $card = Card::where(KARTENVERWALTUNG::KVWKARTENNUMMER, $request->input('cardID'))
            ->first();

        if(!$card) {
            throw new HttpException('Card not found', 404);
        }

        $transaction = $card->recharge(
            $request->input('terminalgroupid_gutschein'),
            $request->input('cancellationAmountCent')
        );

        if($transaction->failed()) {
            throw new HttpException('Could not cancel payment. Please contact support.', 500);
        }
    })->middleware(['AuthenticateWithApi', 'AuthenticateIsPartnerAdminOrUser']);

    //Guthaben aufladen
    Route::post('/add-voucher', function (Request $request) {

        if(!$request->has('cardID') || $request->input('cardID') == '' || strlen($request->input('cardID')) == 0) {
            return returnNewErrorObject('Es wurde keine Kartennummer angegeben.', 'no_cardID', 400);
        }

        if(!$request->has('addVoucherAmountCent') || $request->input('addVoucherAmountCent') == '' || strlen($request->input('addVoucherAmountCent')) == 0) {
            return returnNewErrorObject('Es wurde kein Betrag angegeben.', 'no_voucher_amount', 400);
        }

        $inputCardID = trim($request->input('cardID'));

        $amountCent = $request->input('addVoucherAmountCent');

        $addVoucherResponse = _addVoucher($request, $inputCardID, $amountCent);
        if(isError($addVoucherResponse)) {
            return returnErrorObject($addVoucherResponse);
        }

        return response()->json($addVoucherResponse, 200);

    })->middleware(['AuthenticateWithApi', 'AuthenticateIsPartnerAdminOrUser']);

    //Guthaben einlösen
    Route::post('/redeem-voucher', function (Request $request) {

        if(!$request->has('cardID') || $request->input('cardID') == '' || strlen($request->input('cardID')) == 0) {
            return returnNewErrorObject('Es wurde keine Kartennummer angegeben.', 'no_cardID', 400);
        }

        if(!$request->has('redeemVoucherAmountCent') || $request->input('redeemVoucherAmountCent') == '' || strlen($request->input('redeemVoucherAmountCent')) == 0) {
            return returnNewErrorObject('Es wurde kein Betrag angegeben.', 'no_voucher_amount', 400);
        }

        $cardID = trim($request->input('cardID'));

        $amountCent = $request->input('redeemVoucherAmountCent');

        if(!_isValidAmountCent($amountCent)) {
            return returnNewErrorObject('Es wurde ein ungültiger Betrag angegeben.', 'invalid_amount_cent', 400);
        }

        $redeemVoucherResponse = _redeemVoucher($request, $cardID, $amountCent);

        if(isError($redeemVoucherResponse)) {
            return returnErrorObject($redeemVoucherResponse);
        }

        return response()->json( $redeemVoucherResponse, 200 );

    })->middleware(['AuthenticateWithApi', 'AuthenticateIsPartnerAdminOrUser']);

    //Kundenbonus aufladen
    Route::post('/add-bonus', function (Request $request) {

        if(!$request->has('cardID') || $request->input('cardID') == '' || strlen($request->input('cardID')) == 0) {
            return returnNewErrorObject('Es wurde keine Kartennummer angegeben.', 'no_cardID', 400);
        }

        if(!$request->has('purchaseAmountCent') || $request->input('purchaseAmountCent') == '' || strlen($request->input('purchaseAmountCent')) == 0) {
            return returnNewErrorObject('Es wurde keine Bonusbetrag angegeben.', 'no_amount_cent', 400);
        }

        $cardID = trim($request->input('cardID'));

        $amountCent = $request->input('purchaseAmountCent');

        if(!_isValidAmountCent($amountCent)) {
            return returnNewErrorObject('Es wurde ein ungültiger Betrag angegeben.', 'invalid_amount_cent', 400);
        }

        $addBonusResponse = _addBonus($request, $cardID, $amountCent);
        if(isError($addBonusResponse)) {
            return returnErrorObject($addBonusResponse);
        }

        //to do: check in GW ob kundenbonus gesetzt (achtung: bonus ist nur in zentrale und nicht filiale)

        return response()->json($addBonusResponse, 200);

    })->middleware(['AuthenticateWithApi', 'AuthenticateIsPartnerAdminOrUser']);

    Route::post('/customers/login', function (Request $request) {

        $customerLogin = _handleCustomerLogin($request, true);

        if(isError($customerLogin)) {
            return returnErrorObject($customerLogin);
        }

        $response = new stdClass();
        $response->cardIDs = array_column($customerLogin->cards, 'cardId');
        $response->cards = $customerLogin->cards;
        $response->region = $customerLogin->region;
        $response->cardName = $customerLogin->cardName;
        $response->x_api_token = $customerLogin->jwt;
        //$response->gguid = $customerLogin->gguid;

        return response()->json( $response, 200 );
    });

    Route::get('/partners/transactions', function (Request $request) {

        if($request->has('fromDate') && !empty($request->input('fromDate'))) {
            if(!validateDateIsISOFormat($request->input('fromDate'))) {
                return returnNewErrorObject('Ungültiges "von" Datum. Bitte wenden Sie sich an den Support.', 'invalid_fromDate', 400);
            }
        } else {
            $request->merge(['fromDate' => (new DateTime('today -2 day midnight', new DateTimeZone('Europe/Berlin')))->format('Y-m-d\TH:i:s')]);
        }

        if($request->has('toDate') && !empty($request->input('toDate'))) {
            if(!validateDateIsISOFormat($request->input('toDate'))) {
                return returnNewErrorObject('Ungültiges "bis" Datum. Bitte wenden Sie sich an den Support.', 'invalid_toDate', 400);
            }
        } else {
            $request->merge(['toDate' => (new DateTime('tomorrow -1 second', new DateTimeZone('Europe/Berlin')))->format('Y-m-d\TH:i:s')]);
        }

        $transactions = _handleGetPartnerTransactions($request);
        if(isError($transactions)) {
            return returnErrorObject($transactions);
        }


        return response()->json( $transactions, 200 );
    })->middleware(['AuthenticateWithApi', 'AuthenticateIsPartner']);

    Route::get('/partners/transactions/{transactionGguid}/correction', function (Request $request, string $transactionGguid) {

        $responseToSend = new stdClass();

        $transaction_data = getGwTransactionByGGUID($transactionGguid);

        if(!property_exists($transaction_data, 'GGUID')) {
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Die Transaktion wurde nicht gefunden. Bitte kontaktieren Sie den Support.', 'transaction_not_found', 400);
        }

        $responseToSend->cardID = $transaction_data->TADKARTENNUMMER;

        $bookingType = property_exists($transaction_data, 'TADBUCHUNGSARTUEBERSETZUNG') ? $transaction_data->TADBUCHUNGSARTUEBERSETZUNG : $transaction_data->TADBUCHUNGSART;
        $bookingType = strtolower($bookingType);
        if($bookingType == 'einlösung') {
            $responseToSend->isAddVoucher = false;
            $responseToSend->isRedeemVoucher = true;
            $responseToSend->isAddBonus = false;
            $responseToSend->redeemVoucherAmount = $transaction_data->TADBETRAG;
        } else if($bookingType == 'aufladung') {
            $responseToSend->isAddVoucher = true;
            $responseToSend->isRedeemVoucher = false;
            $responseToSend->isAddBonus = false;
            $responseToSend->addVoucherAmount = $transaction_data->TADBETRAG;
        } else if($bookingType == 'kundenbonus') {
            $responseToSend->isAddVoucher = false;
            $responseToSend->isRedeemVoucher = false;
            $responseToSend->isAddBonus = true;
            $responseToSend->addBonusAmount = $transaction_data->TADBETRAG;
        }

        $responseToSend->bookingTimestamp = convertDateWithFormatToISODate($transaction_data->TADBUCHUNGSDATUM, "Y-m-d\TH:i:s.vP");

        $company_data = getGwPersonalDataByGGUID($request->input('company_gguid'));
        if(!property_exists($company_data, 'GGUID')) {
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Die Firma wurde nicht gefunden. Bitte kontaktieren Sie den Support.', 'company_not_found', 500);
        }
        if(!property_exists($company_data, 'TMPARTNERDATENVOLLSTAENDIG') || $company_data->TMPARTNERDATENVOLLSTAENDIG !== true) {
            return returnNewErrorObject('Sie müssen zuerst Ihre Partnerdaten (Persönliche Daten) vervollständigen, bevor Sie Buchungen vornehmen können und Korrekturbuchungen einreichen können!', 'personal_data_not_complete', 400);
        }

        if(!property_exists($company_data, 'TMVERTRAGID') || $company_data->TMVERTRAGID == '') {
            return returnNewErrorObject('Es wurde keine Vertragsnummer für Ihren Account gefunden! Bitte kontaktieren Sie den Support.', 'no_contract_id', 500);
        }

        $responseToSend->contractID = $company_data->TMVERTRAGID;
        $responseToSend->partnerName = property_exists($company_data, 'COMPNAME') ? $company_data->COMPNAME : '';

        $personal_data = getGwPersonalDataByGGUID($request->input('contact_person_gguid'));
        if(!property_exists($personal_data, 'GGUID')) {
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Ihr Account wurde nicht gefunden. Bitte kontaktieren Sie den Support.', 'no_contact_person', 400);
        }

        $responseToSend->contactName = $personal_data->CHRISTIANNAME . ' ' . $personal_data->NAME;
        $responseToSend->contactEmail = property_exists($personal_data, 'MAILFIELDSTR4') ? $personal_data->MAILFIELDSTR4 : '';

        return response()->json( $responseToSend, 200 );

    })->middleware(['AuthenticateWithApi', 'AuthenticateIsPartner']);

    Route::get('/partners/booking', function (Request $request) {
        $returnFromHandle = _handleGetBooking($request);
        if(isError($returnFromHandle)) {
            return returnErrorObject($returnFromHandle);
        }

        return response()->json( $returnFromHandle, 200 );
    })->middleware(['AuthenticateWithApi', 'AuthenticateIsPartner']);

    Route::get('/partners/bonus', function (Request $request) {
        $response = _handleGetBonus($request);
        if(isError($response)) {
            return returnErrorObject($response);
        }

        return response()->json( $response, 200 );
    })->middleware(['AuthenticateWithApi', 'AuthenticateIsPartner']);

    Route::post('/partners/login', function (Request $request) {

        $partnerLogin = _handlePartnerLogin($request, true);

        if(isError($partnerLogin)) {
            return returnErrorObject($partnerLogin);
        }

        return response()->json( $partnerLogin, 200 );
    });

    Route::post('/partners/correction-booking', function (Request $request) {
        $correctionBooking = _handleCorrectionBooking($request);

        if(isError($correctionBooking)) {
            return returnErrorObject($correctionBooking);
        }

        return response()->json( new stdClass(), 200 );
    })->middleware(['AuthenticateWithApi', 'AuthenticateIsPartner']);

    Route::put('/partners/bonus', function (Request $request) {

        $handle_set_bonus = handleSetBonus($request);

        if(isError($handle_set_bonus)) {
            return returnErrorObject($handle_set_bonus);
        }

        return response()->json( $handle_set_bonus, 200 );
    })->middleware(['AuthenticateWithApi', 'AuthenticateIsPartnerAdmin']);

    Route::get('/customers/faqs', function (Request $request) {

        $customer_faqs = _getFAQs($request, "customer_faqs");
        if(isError($customer_faqs)) {
            return returnErrorObject($customer_faqs);
        }

        return response()->json( $customer_faqs, 200 );
    });

    Route::get('/partners/faqs', function (Request $request) {

        $faqType = "partner_public_faqs";
        if($request->has('faq_type')) {
            if($request->input('faq_type') == 'partner_faqs' || $request->input('faq_type') == 'partner_public_faqs' || $request->input('faq_type') == 'interest_partner_faqs') {
                $faqType = $request->input('faq_type');
            }
        }

        $partner_faqs = _getFAQs($request, $faqType);
        if(isError($partner_faqs)) {
            return returnErrorObject($partner_faqs);
        }

        return response()->json( $partner_faqs, 200 );
    });

    Route::get('/employers/faqs', function (Request $request) {

        $faqType = "employer_public_faqs";
        if($request->has('faq_type')) {
            if($request->input('faq_type') == 'employer_faqs' || $request->input('faq_type') == 'employer_public_faqs' || $request->input('faq_type') == 'interest_employer_faqs') {
                $faqType = $request->input('faq_type');
            }
        }

        $partner_faqs = _getFAQs($request, $faqType);
        if(isError($partner_faqs)) {
            return returnErrorObject($partner_faqs);
        }

        return response()->json( $partner_faqs, 200 );
    });

    Route::get('/partners', function (Request $request) {

        $result = handleGetPartners($request);

        if(isError($result)){
            return returnErrorObject($result);
        }

        return response()->json($result, 200);
    });

    Route::get('/partners/categories', function (Request $request) {
        $categories = _getSuggestedValuesForAddress(['CATEGORY']);

        if(isError($categories)){
            return returnErrorObject($categories);
        }

        return response()->json($categories['CATEGORY'], 200);
    });

    //get customer registration form values
    Route::get('/customers/registration-form-values', function (Request $request) {
        $values = _handleGetCustomerRegistrationFormValues();

        if(isError($values)){
            return returnErrorObject($values);
        }

        return response()->json($values, 200);
    });

    //customer registration
    Route::post('/customers', function (Request $request) {
        $returnFromHandle = _handleCustomerRegistration($request);

        if(isError($returnFromHandle)) {
            return returnErrorObject($returnFromHandle);
        }

        return response()->json($returnFromHandle, 200);
    });

    //update customer
    Route::put('/customers/me', function (Request $request) {

        $updatedCustomerUserData = _handleUpdateCustomerUserData($request);

        if(isError($updatedCustomerUserData)) {
            return returnErrorObject($updatedCustomerUserData);
        }

        $consent_data = handleUpdateCustomerConsents($request);
        if(isError($consent_data)) {
            return returnNewErrorObject('Beim Speichern der Einwilligungserklärungen ist ein Fehler aufgetreten. Ihre anderen Profildaten wurden aber erfolgreich gespeichert.', 'unknown_error_consents', 500);
        }

        return response()->json( new stdClass(), 200 );

    })->middleware(['AuthenticateWithApi', 'AuthenticateIsCustomer']);

    //get current customer personal data
    Route::get('/customers/me', function (Request $request) {

        $personal_data = handleGetCustomerPersonalData($request);
        if(isError($personal_data)) {
            return returnErrorObject($personal_data);
        }

        $cardsData = getCardsForCustomer($personal_data->GGUID);
        if(isError($cardsData)) {
            Log::error("no card_data for " . $personal_data->GGUID . ": " . print_r($cardsData, true));
            return returnNewErrorObject( 'Für Ihren Account wurden keine Karten gefunden. Bitte wenden Sie sich an den Support', 'no_cardIDs', 500);
        }

        $cards = array();
        if(count($cardsData) > 0) {
            foreach ($cardsData as $card) {
                if (is_object($card) && property_exists($card, 'KVWKARTENNUMMER')) {
                    $temp = new stdClass();
                    $temp->cardId = $card->KVWKARTENNUMMER;
                    $temp->isIndividualCard = property_exists($card, 'KVWISTINDIVIDUELLEKARTE') ? $card->KVWISTINDIVIDUELLEKARTE : false;
                    if($temp->isIndividualCard == true && (property_exists($card, 'KVWMODUL') && contains(strtolower('MitarbeiterCARD'), strtolower($card->KVWMODUL)))) {
                        $employer = getEmployerForCardGGUID($card->GGUID);
                        if(isError($employer)) {
                            return $employer;
                        }
                        $temp->mitarbeitercardLogo = 'https://backend.mycity.cards/api/v1/partners/' . $employer->GGUID . '/logo.png';
                    }
                    array_push($cards, $temp);
                }
            }
        }

        $response_to_send = [
            'email' => $personal_data->MAILFIELDSTR3,
            'title' => property_exists($personal_data, 'TITLE') ? $personal_data->TITLE : null,
            'salutation' => $personal_data->ADDRESSTERM,
            'gender' => $personal_data->GWGENDER,
            'firstName'=> $personal_data->CHRISTIANNAME,
            'lastName'=> $personal_data->NAME,
            'street'=> $personal_data->STREET3,
            'zip'=> $personal_data->ZIP3,
            'city'=> $personal_data->TOWN3,
            'country'=> $personal_data->COUNTRY3,
            'phone' => $personal_data->PHONEFIELDSTR7,
            'birthdate'=> $personal_data->BIRTHDAY_ISO,
            'birthdateFormattedDE'=> $personal_data->BIRTHDAY_FORMATTED_DE,
            'marketingAdsConsent' => $personal_data->NCWERBUNGEINWILLIGUNG,
            'newsletterConsent' => $personal_data->NCNLANGEMELDET,
            'cardIDs' => array_column($cards, 'cardId'),
            'cards' => $cards
        ];

        return response()->json( $response_to_send, 200 );
    })->middleware(['AuthenticateWithApi', 'AuthenticateIsCustomer']);

    //delete customer
    Route::delete('/customers/me', function (Request $request) {

        if(!$request->has('alsoDeleteAllCards')) {
            $request->merge(['alsoDeleteAllCards' => true]);
        }

        $deleteCustomerUserData = _handleDeleteUser($request);

        if(isError($deleteCustomerUserData)) {
            return returnErrorObject($deleteCustomerUserData);
        }

        DB::table('mycitycards_sessions')->where('email', $request->input('email'))->delete();

        return response()->json( new stdClass(), 200 );

    })->middleware(['AuthenticateWithApi', 'AuthenticateIsCustomer']);

    //delete partner
    Route::delete('/partners/me', function (Request $request) {
        /*
        $updatedCustomerUserData = _handleUpdateCustomerUserData($request);

        if(isError($updatedCustomerUserData)) {
            return returnErrorObject($updatedCustomerUserData);
        }*/

        return response()->json( new stdClass(), 200 );

    })->middleware(['AuthenticateWithApi', 'AuthenticateIsPartnerAdmin']);

    Route::get('/partners/{partnerGguid}', function (Request $request, string $partnerGguid) {

        $company_data = getGwPersonalDataByGGUID($partnerGguid);
        if(isError($company_data)) {
            return returnErrorObject($company_data);
        }

        $response = new stdClass();
        $response->closedMonday = property_exists($company_data, 'TMPARTNERDATENVOLLSTAENDIG') ? $company_data->TMPARTNERHATGESCHLOSSENMO : true;
        $response->closedTuesday = property_exists($company_data, 'TMPARTNERHATGESCHLOSSENDI') ? $company_data->TMPARTNERHATGESCHLOSSENDI : true;
        $response->closedWednesday = property_exists($company_data, 'TMPARTNERHATGESCHLOSSENMI') ? $company_data->TMPARTNERHATGESCHLOSSENMI : true;
        $response->closedThursday = property_exists($company_data, 'TMPARTNERHATGESCHLOSSENDO') ? $company_data->TMPARTNERHATGESCHLOSSENDO : true;
        $response->closedFriday = property_exists($company_data, 'TMPARTNERHATGESCHLOSSENFR') ? $company_data->TMPARTNERHATGESCHLOSSENFR : true;
        $response->closedSaturday = property_exists($company_data, 'TMPARTNERHATGESCHLOSSENSA') ? $company_data->TMPARTNERHATGESCHLOSSENSA : true;
        $response->closedSunday = property_exists($company_data, 'TMPARTNERHATGESCHLOSSENSO') ? $company_data->TMPARTNERHATGESCHLOSSENSO : true;

        $openingHours = new stdClass();
        $openingHours->mon = [];
        $openingHours->tue = [];
        $openingHours->wed = [];
        $openingHours->thu = [];
        $openingHours->fri = [];
        $openingHours->sat = [];
        $openingHours->sun = [];
        $openingHours->mon = [];
        if(property_exists($company_data, 'TMOEFFZEITMONTAG1VON') && !empty($company_data->TMOEFFZEITMONTAG1VON) && property_exists($company_data, 'TMOEFFZEITMONTAG1BIS') && !empty($company_data->TMOEFFZEITMONTAG1BIS)) {
            $temp = new stdClass();
            $temp->start = property_exists($company_data, 'TMOEFFZEITMONTAG1VON') ? $company_data->TMOEFFZEITMONTAG1VON : '';
            $temp->end = property_exists($company_data, 'TMOEFFZEITMONTAG1BIS') ? $company_data->TMOEFFZEITMONTAG1BIS : '';
            $openingHours->mon[] = $temp;
        }
        if(property_exists($company_data, 'TMOEFFZEITMONTAG2VON') && !empty($company_data->TMOEFFZEITMONTAG2VON) && property_exists($company_data, 'TMOEFFZEITMONTAG2BIS') && !empty($company_data->TMOEFFZEITMONTAG2BIS)) {
            $temp = new stdClass();
            $temp->start = property_exists($company_data, 'TMOEFFZEITMONTAG2VON') ? $company_data->TMOEFFZEITMONTAG2VON : '';
            $temp->end = property_exists($company_data, 'TMOEFFZEITMONTAG2BIS') ? $company_data->TMOEFFZEITMONTAG2BIS : '';
            $openingHours->mon[] = $temp;
        }
        if(property_exists($company_data, 'TMOEFFZEITDIENSTAG1VON') && !empty($company_data->TMOEFFZEITDIENSTAG1VON) && property_exists($company_data, 'TMOEFFZEITDIENSTAG1BIS') && !empty($company_data->TMOEFFZEITDIENSTAG1BIS)) {
            $temp = new stdClass();
            $temp->start = property_exists($company_data, 'TMOEFFZEITDIENSTAG1VON') ? $company_data->TMOEFFZEITDIENSTAG1VON : '';
            $temp->end = property_exists($company_data, 'TMOEFFZEITDIENSTAG1BIS') ? $company_data->TMOEFFZEITDIENSTAG1BIS : '';
            $openingHours->tue[] = $temp;
        }
        if(property_exists($company_data, 'TMOEFFZEITDIENSTAG2VON') && !empty($company_data->TMOEFFZEITDIENSTAG2VON) && property_exists($company_data, 'TMOEFFZEITDIENSTAG2BIS') && !empty($company_data->TMOEFFZEITDIENSTAG2BIS)) {
            $temp = new stdClass();
            $temp->start = property_exists($company_data, 'TMOEFFZEITDIENSTAG2VON') ? $company_data->TMOEFFZEITDIENSTAG2VON : '';
            $temp->end = property_exists($company_data, 'TMOEFFZEITDIENSTAG2BIS') ? $company_data->TMOEFFZEITDIENSTAG2BIS : '';
            $openingHours->tue[] = $temp;
        }
        if(property_exists($company_data, 'TMOEFFZEITMITTWOCH1VON') && !empty($company_data->TMOEFFZEITMITTWOCH1VON) && property_exists($company_data, 'TMOEFFZEITMITTWOCH1BIS') && !empty($company_data->TMOEFFZEITMITTWOCH1BIS)) {
            $temp = new stdClass();
            $temp->start = property_exists($company_data, 'TMOEFFZEITMITTWOCH1VON') ? $company_data->TMOEFFZEITMITTWOCH1VON : '';
            $temp->end = property_exists($company_data, 'TMOEFFZEITMITTWOCH1BIS') ? $company_data->TMOEFFZEITMITTWOCH1BIS : '';
            $openingHours->wed[] = $temp;
        }
        if(property_exists($company_data, 'TMOEFFZEITMITTWOCH2VON') && !empty($company_data->TMOEFFZEITMITTWOCH2VON) && property_exists($company_data, 'TMOEFFZEITMITTWOCH2BIS') && !empty($company_data->TMOEFFZEITMITTWOCH2BIS)) {
            $temp = new stdClass();
            $temp->start = property_exists($company_data, 'TMOEFFZEITMITTWOCH2VON') ? $company_data->TMOEFFZEITMITTWOCH2VON : '';
            $temp->end = property_exists($company_data, 'TMOEFFZEITMITTWOCH2BIS') ? $company_data->TMOEFFZEITMITTWOCH2BIS : '';
            $openingHours->wed[] = $temp;
        }
        if(property_exists($company_data, 'TMOEFFZEITDONNERSTAG1VON') && !empty($company_data->TMOEFFZEITDONNERSTAG1VON) && property_exists($company_data, 'TMOEFFZEITDONNERSTAG1BIS') && !empty($company_data->TMOEFFZEITDONNERSTAG1BIS)) {
            $temp = new stdClass();
            $temp->start = property_exists($company_data, 'TMOEFFZEITDONNERSTAG1VON') ? $company_data->TMOEFFZEITDONNERSTAG1VON : '';
            $temp->end = property_exists($company_data, 'TMOEFFZEITDONNERSTAG1BIS') ? $company_data->TMOEFFZEITDONNERSTAG1BIS : '';
            $openingHours->thu[] = $temp;
        }
        if(property_exists($company_data, 'TMOEFFZEITDONNERSTAG2VON') && !empty($company_data->TMOEFFZEITDONNERSTAG2VON) && property_exists($company_data, 'TMOEFFZEITDONNERSTAG2BIS') && !empty($company_data->TMOEFFZEITDONNERSTAG2BIS)) {
            $temp = new stdClass();
            $temp->start = property_exists($company_data, 'TMOEFFZEITDONNERSTAG2VON') ? $company_data->TMOEFFZEITDONNERSTAG2VON : '';
            $temp->end = property_exists($company_data, 'TMOEFFZEITDONNERSTAG2BIS') ? $company_data->TMOEFFZEITDONNERSTAG2BIS : '';
            $openingHours->thu[] = $temp;
        }
        if(property_exists($company_data, 'TMOEFFZEITFREITAG1VON') && !empty($company_data->TMOEFFZEITFREITAG1VON) && property_exists($company_data, 'TMOEFFZEITFREITAG1BIS') && !empty($company_data->TMOEFFZEITFREITAG1BIS)) {
            $temp = new stdClass();
            $temp->start = property_exists($company_data, 'TMOEFFZEITFREITAG1VON') ? $company_data->TMOEFFZEITFREITAG1VON : '';
            $temp->end = property_exists($company_data, 'TMOEFFZEITFREITAG1BIS') ? $company_data->TMOEFFZEITFREITAG1BIS : '';
            $openingHours->fri[] = $temp;
        }
        if(property_exists($company_data, 'TMOEFFZEITFREITAG2VON') && !empty($company_data->TMOEFFZEITFREITAG2VON) && property_exists($company_data, 'TMOEFFZEITFREITAG2BIS') && !empty($company_data->TMOEFFZEITFREITAG2BIS)) {
            $temp = new stdClass();
            $temp->start = property_exists($company_data, 'TMOEFFZEITFREITAG2VON') ? $company_data->TMOEFFZEITFREITAG2VON : '';
            $temp->end = property_exists($company_data, 'TMOEFFZEITFREITAG2BIS') ? $company_data->TMOEFFZEITFREITAG2BIS : '';
            $openingHours->fri[] = $temp;
        }
        if(property_exists($company_data, 'TMOEFFZEITSAMSTAG1VON') && !empty($company_data->TMOEFFZEITSAMSTAG1VON) && property_exists($company_data, 'TMOEFFZEITSAMSTAG1BIS') && !empty($company_data->TMOEFFZEITSAMSTAG1BIS)) {
            $temp = new stdClass();
            $temp->start = property_exists($company_data, 'TMOEFFZEITSAMSTAG1VON') ? $company_data->TMOEFFZEITSAMSTAG1VON : '';
            $temp->end = property_exists($company_data, 'TMOEFFZEITSAMSTAG1BIS') ? $company_data->TMOEFFZEITSAMSTAG1BIS : '';
            $openingHours->sat[] = $temp;
        }
        if(property_exists($company_data, 'TMOEFFZEITSAMSTAG2VON') && !empty($company_data->TMOEFFZEITSAMSTAG2VON) && property_exists($company_data, 'TMOEFFZEITSAMSTAG2BIS') && !empty($company_data->TMOEFFZEITSAMSTAG2BIS)) {
            $temp = new stdClass();
            $temp->start = property_exists($company_data, 'TMOEFFZEITSAMSTAG2VON') ? $company_data->TMOEFFZEITSAMSTAG2VON : '';
            $temp->end = property_exists($company_data, 'TMOEFFZEITSAMSTAG2BIS') ? $company_data->TMOEFFZEITSAMSTAG2BIS : '';
            $openingHours->sat[] = $temp;
        }
        if(property_exists($company_data, 'TMOEFFZEITSONNTAG1VON') && !empty($company_data->TMOEFFZEITSONNTAG1VON) && property_exists($company_data, 'TMOEFFZEITSONNTAG1BIS') && !empty($company_data->TMOEFFZEITSONNTAG1BIS)) {
            $temp = new stdClass();
            $temp->start = property_exists($company_data, 'TMOEFFZEITSONNTAG1VON') ? $company_data->TMOEFFZEITSONNTAG1VON : '';
            $temp->end = property_exists($company_data, 'TMOEFFZEITSONNTAG1BIS') ? $company_data->TMOEFFZEITSONNTAG1BIS : '';
            $openingHours->sun[] = $temp;
        }
        if(property_exists($company_data, 'TMOEFFZEITSONNTAG2VON') && !empty($company_data->TMOEFFZEITSONNTAG2VON) && property_exists($company_data, 'TMOEFFZEITSONNTAG2BIS') && !empty($company_data->TMOEFFZEITSONNTAG2BIS)) {
            $temp = new stdClass();
            $temp->start = property_exists($company_data, 'TMOEFFZEITSONNTAG2VON') ? $company_data->TMOEFFZEITSONNTAG2VON : '';
            $temp->end = property_exists($company_data, 'TMOEFFZEITSONNTAG2BIS') ? $company_data->TMOEFFZEITSONNTAG2BIS : '';
            $openingHours->sun[] = $temp;
        }

        $response->openingHours = $openingHours;
        $response->companyOpenHoursAdditionalInfo = property_exists($company_data, 'TMINFOOEFFNUNGSZEIT') ? $company_data->TMINFOOEFFNUNGSZEIT : '';
        $response->companyOpenHoursOnlyByArrangement = property_exists($company_data, 'TMTERMINVEREINBARUNG') ? $company_data->TMTERMINVEREINBARUNG : false;

        $response->companyName = property_exists($company_data, 'COMPNAME2') ? $company_data->COMPNAME2 : "";
        $response->category = property_exists($company_data, 'CATEGORY') ? $company_data->CATEGORY : "";
        $response->city = property_exists($company_data, 'TOWN2') ? $company_data->TOWN2 : "";
        $response->street = property_exists($company_data, 'STREET2') ? $company_data->STREET2 : "";
        $response->zip = property_exists($company_data, 'ZIP2') ? $company_data->ZIP2 : "";
        $response->country = property_exists($company_data, 'COUNTRY2') ? $company_data->COUNTRY2 : "";
        $response->phone = property_exists($company_data, 'TMPHONEVEROEFFENTLICHUNG') ? $company_data->TMPHONEVEROEFFENTLICHUNG : "";
        $response->email = property_exists($company_data, 'TMMAILVEROEFFENTLICHUNG') ? $company_data->TMMAILVEROEFFENTLICHUNG : "";
        if(property_exists($company_data, 'WWWFIELDSTR1')) {
            if(str_starts_with(strtolower($company_data->WWWFIELDSTR1), 'http')) {
                $response->website = $company_data->WWWFIELDSTR1;
            } else {
                $response->website = 'https://' . $company_data->WWWFIELDSTR1;
            }
        } else {
            $response->website = "";
        }
        $response->latitude = property_exists($company_data, 'GWLATITUDE') ? $company_data->GWLATITUDE : 0;
        $response->longitude = property_exists($company_data, 'GWLONGITUDE') ? $company_data->GWLONGITUDE : 0;
        $response->categories = property_exists($company_data, 'CATEGORY') ? explode(', ', $company_data->CATEGORY) : [];
        $response->canAddVoucher = property_exists($company_data, 'TMISTAUFLADESTELLE') ? $company_data->TMISTAUFLADESTELLE : false;
        $response->canRedeemVoucher = property_exists($company_data, 'TMISTEINLOESESTELLE') ? $company_data->TMISTEINLOESESTELLE : false;
        $response->instagramUrl = property_exists($company_data, 'TMURLINSTAGRAM') ? $company_data->TMURLINSTAGRAM : null;
        $response->facebookUrl = property_exists($company_data, 'TMURLFACEBOOK') ? $company_data->TMURLFACEBOOK : null;
        $response->calendarBookingUrl = property_exists($company_data, 'TMURLKALENDERTERMINBUCHUNG') ? $company_data->TMURLKALENDERTERMINBUCHUNG : null;
        $response->infoText = property_exists($company_data, 'TMINFOTEXTGESCHAEFT') ? $company_data->TMINFOTEXTGESCHAEFT : null;

        $response->anyBonusActive = property_exists($company_data, 'TMBONUSAKTIVIERT') ? $company_data->TMBONUSAKTIVIERT : false;
        if($response->anyBonusActive == true) {
            $response->permanentBonusActive = property_exists($company_data, 'TMDAUERBONUS') ? $company_data->TMDAUERBONUS : false;
            if($response->permanentBonusActive == true) {
                $response->permanentBonusType = property_exists($company_data, 'TMDAUERBONUSART') ? $company_data->TMDAUERBONUSART : "";
                $response->permanentBonusPercent = property_exists($company_data, 'TMDBONUSINPROZENT') ? number_format($company_data->TMDBONUSINPROZENT, 2, ',', '.') : NULL;
                $response->permanentBonusPercentMinSale = property_exists($company_data, 'TMDBONUSPROZENTMINDESTUMSATZ') ? number_format($company_data->TMDBONUSPROZENTMINDESTUMSATZ, 2, ',', '.') : NULL;
                $response->permanentBonusAmount = property_exists($company_data, 'TMDBONUSBETRAG') ? number_format($company_data->TMDBONUSBETRAG, 2, ',', '.') : NULL;
                $response->permanentBonusAmountMinSale = property_exists($company_data, 'TMDBONUSBETRAGMINDESTUMSATZ') ? number_format($company_data->TMDBONUSBETRAGMINDESTUMSATZ, 2, ',', '.') : NULL;
                $response->permanentBonusForEntirePurchase = property_exists($company_data, 'TMDBONUSEINKAUFGESAMT') ? $company_data->TMDBONUSEINKAUFGESAMT : 'Nein';
                $response->permanentBonusExceptText = property_exists($company_data, 'TMDAUERBONUSAUSSERAUF') ? $company_data->TMDAUERBONUSAUSSERAUF : NULL;
                $response->permanentBonusOnlyText = property_exists($company_data, 'TMDAUERBONUSNURAUF') ? $company_data->TMDAUERBONUSNURAUF : NULL;
                $response->permanentBonusOnlyForSpecificTimes = (property_exists($company_data, 'TMDBONUSZEITSTEUERUNG') and $company_data->TMDBONUSZEITSTEUERUNG != 'Nein') ? $company_data->TMDBONUSZEITSTEUERUNG : 'Nein';
                $response->permanentBonusInfoText = '';
                if($response->permanentBonusOnlyForSpecificTimes != NULL && $response->permanentBonusOnlyForSpecificTimes != 'Nein') {
                    $response->permanentBonusInfoText .= 'In der Zeit: ' . $response->permanentBonusOnlyForSpecificTimes . ', ';
                }
                if($response->permanentBonusForEntirePurchase == 'Ja' || $response->permanentBonusForEntirePurchase === true) {
                    if($response->permanentBonusType == 'Prozentualer Bonus vom Einkaufswert') {
                        $response->permanentBonusInfoText .= $response->permanentBonusPercent . '% auf ihren Einkauf';
                    } else if($response->permanentBonusType == 'Prozentualer Bonus vom Einkaufswert in Kombination mit einem Mindestumsatz') {
                        $response->permanentBonusInfoText .= $response->permanentBonusPercent . '% auf ihren Einkauf ab einem Mindestumsatz von ' . $response->permanentBonusPercentMinSale . '€';
                    } else if($response->permanentBonusType == 'Festbetrag') {
                        $response->permanentBonusInfoText .= $response->permanentBonusAmount . '€ auf ihren Einkauf';
                    } else if($response->permanentBonusType == 'Festbetrag in Kombination mit einem Mindestumsatz') {
                        $response->permanentBonusInfoText .= $response->permanentBonusAmount . '€ auf ihren Einkauf ab einem Mindestumsatz von ' . $response->permanentBonusAmountMinSale . '€';
                    }
                } else {
                    if($response->permanentBonusType == 'Prozentualer Bonus vom Einkaufswert') {
                        $response->permanentBonusInfoText .= $response->permanentBonusPercent . '%';
                    } else if($response->permanentBonusType == 'Prozentualer Bonus vom Einkaufswert in Kombination mit einem Mindestumsatz') {
                        $response->permanentBonusInfoText .= $response->permanentBonusPercent . '% ab einem Mindestumsatz von ' . $response->permanentBonusPercentMinSale . '€';
                    } else if($response->permanentBonusType == 'Festbetrag') {
                        $response->permanentBonusInfoText .= $response->permanentBonusAmount . '€';
                    } else if($response->permanentBonusType == 'Festbetrag in Kombination mit einem Mindestumsatz') {
                        $response->permanentBonusInfoText .= $response->permanentBonusAmount . '€ ab einem Mindestumsatz von ' . $response->permanentBonusAmountMinSale . '€';
                    }

                    if($response->permanentBonusForEntirePurchase == 'Nein') {
                        if($response->permanentBonusOnlyText !== NULL && !empty($response->permanentBonusOnlyText)) {
                            $response->permanentBonusInfoText .= ', nur auf ' . $response->permanentBonusOnlyText;
                        }
                        if($response->permanentBonusExceptText !== NULL && !empty($response->permanentBonusExceptText)) {
                            $response->permanentBonusInfoText .= ', außer auf ' . $response->permanentBonusExceptText;
                        }
                    } else {
                        $response->permanentBonusInfoText .= ', außer: ' . $response->permanentBonusForEntirePurchase;
                    }
                }
            }
            $response->promotionalBonusActive = property_exists($company_data, 'TMAKTIONSBONUS') ? $company_data->TMAKTIONSBONUS : false;
            if($response->promotionalBonusActive == true) {
                $response->promotionalBonusType = property_exists($company_data, 'TMAKTIONSBONUSART') ? $company_data->TMAKTIONSBONUSART : "";
                $response->promotionalBonusPercent = property_exists($company_data, 'TMABONUSINPROZENT') ? number_format($company_data->TMABONUSINPROZENT, 2, ',', '.') : NULL;
                $response->promotionalBonusPercentMinSale = property_exists($company_data, 'TMABONUSPROZENTMINDESTUMSATZ') ? number_format($company_data->TMABONUSPROZENTMINDESTUMSATZ, 2, ',', '.') : NULL;
                $response->promotionalBonusAmount = property_exists($company_data, 'TMABONUSBETRAG') ? number_format($company_data->TMABONUSBETRAG, 2, ',', '.') : NULL;
                $response->promotionalBonusAmountMinSale = property_exists($company_data, 'TMABONUSBETRAGMINDESTUMSATZ') ? number_format($company_data->TMABONUSBETRAGMINDESTUMSATZ, 2, ',', '.') : NULL;
                $response->promotionalBonusForEntirePurchase = property_exists($company_data, 'TMABONUSEINKAUFGESAMT') ? $company_data->TMABONUSEINKAUFGESAMT : 'Nein';
                $response->promotionalBonusExceptText = property_exists($company_data, 'TMAKTIONSBONUSAUSSERAUF') ? $company_data->TMAKTIONSBONUSAUSSERAUF : NULL;
                $response->promotionalBonusOnlyText = property_exists($company_data, 'TMAKTIONSBONUSNURAUF') ? $company_data->TMAKTIONSBONUSNURAUF : NULL;
                $response->promotionalBonusOnlyForSpecificTimes = (property_exists($company_data, 'TMABONUSZEITSTEUERUNG') and $company_data->TMABONUSZEITSTEUERUNG != 'Nein') ? $company_data->TMABONUSZEITSTEUERUNG : 'Nein';
                $response->promotionalBonusStartDate = property_exists($company_data, 'TMABONUSSTARTDATUM') ? $company_data->TMABONUSSTARTDATUM : NULL;
                $response->promotionalBonusEndDate = property_exists($company_data, 'TMABONUSENDDATUM') ? $company_data->TMABONUSENDDATUM : NULL;
                $response->promotionalBonusInfoText = '';
                if($response->promotionalBonusStartDate != NULL) {
                    $response->promotionalBonusInfoText .= 'Von: ' . convertISODateToGermanDate($response->promotionalBonusStartDate);
                    if($response->promotionalBonusEndDate != NULL) {
                        $response->promotionalBonusInfoText .= ' bis ' . convertISODateToGermanDate($response->promotionalBonusEndDate) . ', ';
                    } else {
                        $response->promotionalBonusInfoText .= ', ';
                    }
                }
                if($response->promotionalBonusOnlyForSpecificTimes != NULL && $response->promotionalBonusOnlyForSpecificTimes != 'Nein') {
                    $response->promotionalBonusInfoText .= 'In der Zeit: ' . $response->promotionalBonusOnlyForSpecificTimes . ', ';
                }
                if($response->promotionalBonusForEntirePurchase == 'Ja' || $response->promotionalBonusForEntirePurchase === true) {
                    if($response->promotionalBonusType == 'Prozentualer Bonus vom Einkaufswert') {
                        $response->promotionalBonusInfoText .= $response->promotionalBonusPercent . '% auf ihren Einkauf';
                    } else if($response->promotionalBonusType == 'Prozentualer Bonus vom Einkaufswert in Kombination mit einem Mindestumsatz') {
                        $response->promotionalBonusInfoText .= $response->promotionalBonusPercent . '% auf ihren Einkauf ab einem Mindestumsatz von ' . $response->promotionalBonusPercentMinSale . '€';
                    } else if($response->promotionalBonusType == 'Festbetrag') {
                        $response->promotionalBonusInfoText .= $response->promotionalBonusAmount . '€ auf ihren Einkauf';
                    } else if($response->promotionalBonusType == 'Festbetrag in Kombination mit einem Mindestumsatz') {
                        $response->promotionalBonusInfoText .= $response->promotionalBonusAmount . '€ auf ihren Einkauf ab einem Mindestumsatz von ' . $response->promotionalBonusAmountMinSale . '€';
                    }
                } else {
                    if($response->promotionalBonusType == 'Prozentualer Bonus vom Einkaufswert') {
                        $response->promotionalBonusInfoText .= $response->promotionalBonusPercent . '%';
                    } else if($response->promotionalBonusType == 'Prozentualer Bonus vom Einkaufswert in Kombination mit einem Mindestumsatz') {
                        $response->promotionalBonusInfoText .= $response->promotionalBonusPercent . '% ab einem Mindestumsatz von ' . $response->promotionalBonusPercentMinSale . '€';
                    } else if($response->promotionalBonusType == 'Festbetrag') {
                        $response->promotionalBonusInfoText .= $response->promotionalBonusAmount . '€';
                    } else if($response->promotionalBonusType == 'Festbetrag in Kombination mit einem Mindestumsatz') {
                        $response->promotionalBonusInfoText .= $response->promotionalBonusAmount . '€ ab einem Mindestumsatz von ' . $response->promotionalBonusAmountMinSale . '€';
                    }

                    if($response->promotionalBonusForEntirePurchase == 'Nein') {
                        if($response->promotionalBonusOnlyText !== NULL && !empty($response->promotionalBonusOnlyText)) {
                            $response->promotionalBonusInfoText .= ', nur auf ' . $response->promotionalBonusOnlyText;
                        }
                        if($response->promotionalBonusExceptText !== NULL && !empty($response->promotionalBonusExceptText)) {
                            $response->promotionalBonusInfoText .= ', außer auf ' . $response->promotionalBonusExceptText;
                        }
                    } else {
                        $response->promotionalBonusInfoText .= ', außer: ' . $response->promotionalBonusForEntirePurchase;
                    }
                }
            }
        }

        $response->logoUrl = 'https://backend.mycity.cards/api/v1/partners/' . $company_data->GGUID . '/logo.png';

        $featured_images = getDocumentsForCompany($company_data->GGUID, ['titelbild'], ['jpg', 'jpeg', 'png'], 'empfangen');

        $gwBonusResponse = Http::withHeaders([
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
            'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
        ])->post(env('GW_API_BASE') . '/query', [
            "query" => "SELECT b.*, a.GGUID as ADDRESSGGUID FROM BONI b LINK_JOIN(linkattribute='TMBONUS') ADDRESS a WHERE a.GGUID = 0x" . $company_data->GGUID . " AND b.GWSSTATUS = 'aktiviert' ORDER BY GWSTYPE"
        ]);

        if($gwBonusResponse->successful()) {
            if(count(json_decode($gwBonusResponse)) > 0) {
                $gwBonusData = json_decode($gwBonusResponse)[0]->rows;

                if (count($gwBonusData) > 0) {
                    foreach ($gwBonusData as $bonus) {
                        $response->anyBonusActive = true;
                        if (!property_exists($response, 'boni')) {
                            $response->boni = [];
                        }
                        $tempBoni = formatBoniObject($bonus);
                        array_push($response->boni, $tempBoni);
                    }
                }
            }
        }

        if(!is_array($featured_images) && property_exists($featured_images, 'errorMessage') && !empty($featured_images->errorMessage)) {
            return response()->json( $featured_images, 500 );
        } else {
            if(is_array($featured_images)) {
                if(count($featured_images) > 0) {
                    $response->featuredImageUrl = 'https://backend.mycity.cards/api/v1/partners/' . $featured_images[0]->gguid . '/titelbild.jpg';
                } else {
                    $response->featuredImageUrl = getCardNameImageUrl($request->input('card_name'));
                }
            } else {
                $response->featuredImageUrl = getCardNameImageUrl($request->input('card_name'));
            }
        }

        return response()->json( $response, 200 );
    });

    Route::get('/partners/{documentGguid}/titelbild.jpg', function (Request $request, string $documentGguid) {

        $gwGetFeatuedImage = Http::withHeaders([
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
            'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
        ])->get(env('GW_API_BASE') . '/type/document/' . $documentGguid . '/file');


        if($gwGetFeatuedImage->successful()) {
            return response($gwGetFeatuedImage->body())->header('Content-Type', 'image/jpeg');
        }

        if($gwGetFeatuedImage->failed()) {
            return returnFallbackImage('card', $request->input('card_name'));
        }
    })->withoutMiddleware([AuthenticateWithApiKey::class])->middleware(CheckIfApiKeyForRegion::class);




    Route::get('/debug/documents-for-company/{companyGguid}', function (Request $request, string $companyGguid) {

    $companyGguid = preg_replace('/^0x/i', '', $companyGguid);

    $gwResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->get(
        env('GW_API_BASE') . '/type/document/full?linked-to=0x' .
        $companyGguid .
        '&linked-to-type=ADDRESS&order-by=DOCDATE desc'
    );

    if ($gwResponse->failed()) {
        return response()->json([
            'status' => $gwResponse->status(),
            'body' => $gwResponse->body(),
        ], 500);
    }

    $documents = json_decode($gwResponse->body());

    $result = [];

    foreach ($documents as $document) {
        $row = $document->fields ?? new stdClass();

        $result[] = [
            'GGUID' => $row->GGUID ?? null,
            'GWSTYPE' => $row->GWSTYPE ?? null,
            'GWFILETYPE' => $row->GWFILETYPE ?? null,
            'GWSSTATUS' => $row->GWSSTATUS ?? null,
            'DOCDATE' => $row->DOCDATE ?? null,
            'NOTES' => $row->NOTES ?? null,
            'would_match_titelbild_filter' =>
                isset($row->GWSTYPE, $row->GWFILETYPE, $row->GWSSTATUS)
                && strtolower($row->GWSTYPE) === 'titelbild'
                && in_array(strtolower($row->GWFILETYPE), ['jpg', 'jpeg', 'png'])
                && strtolower($row->GWSSTATUS) === 'empfangen',
        ];
    }

    return response()->json($result, 200);

})->withoutMiddleware([AuthenticateWithApiKey::class])->middleware(CheckIfApiKeyForRegion::class);

    Route::get('/cards-images/{card_name_slugifyed}.png', function (Request $request, string $card_name_slugifyed) {
        return returnFallbackImage('card', $card_name_slugifyed);
    })->withoutMiddleware([AuthenticateWithApiKey::class]);

    Route::get('/events', function (Request $request) {

        if(!$request->has('white_label_website_url') || $request->input('white_label_website_url') == NULL || $request->input('white_label_website_url') == '') {
            Log::error('[API]: /events: Es wurde keine white_label_website_url in der Session gefunden.');
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'no_url', 400 );
        }

        $eventsArray = array();
        
        for ($i=1; $i < 6; $i++) { 
            $getEvents = Http::withHeaders([
                'Content-Type' => 'application/json; charset=utf-8',
                'Accept' => 'application/json',
            ])->get($request->input('white_label_website_url') . 'wp-json/wp/v2/veranstaltungen?_fields=acf,id,date,date_gmt,modified,modified_gmt,status,link,title,content,excerpt,featured_media,featured_image_url,categories&per_page=100&page=' . $i);

            if($i == 1 && $getEvents->failed()) {
                Log::error('[API]: ' . $getEvents->body());
                return returnNewErrorObject('Es ist ein Fehler aufgetreten. Die Regionsdaten konnten nicht abgerufen werden. Bitte wenden Sie sich an den Support.', 'error_checking_region', 500 );
            }

            if($i > 1 && $getEvents->failed()) {
                // silent fail, reached max amount of events / pages
                break;
            }

            $events = json_decode($getEvents->body());
            
            foreach($events as $event) {
                $temp = new stdClass();

                $temp->id = property_exists($event, 'id') ? $event->id : -1;
                if(property_exists($event, 'date') && !empty($event->date)) {
                    $temp->date = convertDateWithFormatToISODate($event->date, "Y-m-d\TH:i:s");
                } else {
                    $temp->date = '';
                }

                if(property_exists($event, 'modified') && !empty($event->modified)) {
                    $temp->modified = convertDateWithFormatToISODate($event->modified, "Y-m-d\TH:i:s");
                } else {
                    $temp->modified = '';
                }

                $temp->status = property_exists($event, 'status') ? $event->status : "";
                $temp->link = property_exists($event, 'link') ? $event->link : "";

                $temp->title = (property_exists($event, 'title') and property_exists($event->title, 'rendered')) ?
                    html_entity_decode($event->title->rendered) : "";
                $temp->content = (property_exists($event, 'content') and property_exists($event->content, 'rendered')) ? $event->content->rendered : "";
                $temp->excerpt = (property_exists($event, 'excerpt') and property_exists($event->excerpt, 'rendered')) ? $event->excerpt->rendered : "";

                if(property_exists($event, 'acf') && property_exists($event->acf, 'startzeitpunkt') && !empty($event->acf->startzeitpunkt)) {
                    if(validateDateIsUnixEpoch($event->acf->startzeitpunkt)) {
                        $temp->start_date = convertDateWithFormatToISODate($event->acf->startzeitpunkt, "U");
                    } else {
                        if(validateDateIsISOFormat($event->acf->startzeitpunkt)) {
                            $temp->start_date = $event->acf->startzeitpunkt;
                        } else if(validateDate($event->acf->startzeitpunkt, "Y-m-d H:i")) {
                            $temp->start_date = convertDateWithFormatToISODate($event->acf->startzeitpunkt, "Y-m-d H:i");
                        } else {
                            $temp->start_date = convertDateWithFormatToISODate($event->acf->startzeitpunkt, "Y-m-d H:i:s");
                        }
                    }
                } else {
                    $temp->start_date = '';
                }

                if(property_exists($event, 'acf') && property_exists($event->acf, 'endzeitpunkt') && !empty($event->acf->endzeitpunkt)) {
                    if(validateDateIsUnixEpoch($event->acf->endzeitpunkt)) {
                        $temp->end_date = convertDateWithFormatToISODate($event->acf->endzeitpunkt, "U");
                    } else {
                        if(validateDateIsISOFormat($event->acf->endzeitpunkt)) {
                            $temp->end_date = $event->acf->endzeitpunkt;
                        } else if(validateDate($event->acf->endzeitpunkt, "Y-m-d H:i")) {
                            $temp->end_date = convertDateWithFormatToISODate($event->acf->endzeitpunkt, "Y-m-d H:i");
                        } else {
                            $temp->end_date = convertDateWithFormatToISODate($event->acf->endzeitpunkt, "Y-m-d H:i:s");
                        }
                    }
                } else {
                    $temp->end_date = $temp->start_date;
                }

                if(property_exists($event, 'acf') && property_exists($event->acf, 'veranstaltungsort_name') && !empty($event->acf->veranstaltungsort_name)) {
                    $temp->location_name = $event->acf->veranstaltungsort_name;
                } else {
                    $temp->location_name = '';
                }

                if(property_exists($event, 'acf') && property_exists($event->acf, 'veranstaltungsort_strasse') && !empty($event->acf->veranstaltungsort_strasse)) {
                    $temp->location_street = $event->acf->veranstaltungsort_strasse;
                } else {
                    $temp->location_street = '';
                }

                if(property_exists($event, 'acf') && property_exists($event->acf, 'veranstaltungsort_plz') && !empty($event->acf->veranstaltungsort_plz)) {
                    $temp->location_zip = $event->acf->veranstaltungsort_plz;
                } else {
                    $temp->location_zip = '';
                }

                if(property_exists($event, 'acf') && property_exists($event->acf, 'veranstaltungsort_stadt') && !empty($event->acf->veranstaltungsort_stadt)) {
                    $temp->location_city = $event->acf->veranstaltungsort_stadt;
                } else {
                    $temp->location_city = '';
                }

                $temp->featured_image = (property_exists($event, 'featured_image_url') and $event->featured_image_url !== false && !empty($event->featured_image_url)) ? $event->featured_image_url : getCardNameImageUrl($request->input('card_name'));

                if(property_exists($event, 'acf') && is_object($event->acf) && property_exists($event->acf, 'type') && !empty($event->acf->type)) {
                    $temp->type = $event->acf->type;
                    if(property_exists($event, 'acf') && property_exists($event->acf, 'externe_url') && !empty($event->acf->externe_url)) {
                        $temp->external_url = $event->acf->externe_url;
                    } else {
                        $temp->external_url = null;
                        $temp->type = 'internal';
                    }
                } else {
                    $temp->type = 'internal';
                    $temp->external_url = null;
                }

                if(property_exists($event, 'acf') && is_object($event->acf) && property_exists($event->acf, 'price') && !empty($event->acf->price)) {
                    $temp->price = $event->acf->price;
                } else {
                    $temp->price = '';
                }

                $temp->categories = property_exists($event, 'categories') ? $event->categories : [];

                array_push($eventsArray, $temp);
            }
        }

        usort($eventsArray, function ($a, $b) {
            return strcmp($a->start_date, $b->start_date);
        });

        return response()->json( $eventsArray, 200 );
    });

    Route::get('/events/{id}', function (Request $request, string $id) {

        if(!$request->has('white_label_website_url') || $request->input('white_label_website_url') == NULL || $request->input('white_label_website_url') == '') {
            Log::error('[API]: /events: Es wurde keine white_label_website_url in der Session gefunden.');
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'no_url', 400 );
        }

        $getEvents = Http::withHeaders([
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
        ])->get($request->input('white_label_website_url') . 'wp-json/wp/v2/veranstaltungen/' . $id . '?_fields=acf,id,date,date_gmt,modified,modified_gmt,status,link,title,content,excerpt,featured_media,featured_image_url,categories');

        if($getEvents->failed()) {
            Log::error('[API]: ' . $getEvents->body());
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Die Regionsdaten konnten nicht abgerufen werden. Bitte wenden Sie sich an den Support.', 'error_checking_region', 500 );
        }

        $eventDetail = json_decode($getEvents->body());
        $response = new stdClass();

        $response->id = property_exists($eventDetail, 'id') ? $eventDetail->id : -1;
        if(property_exists($eventDetail, 'date') && !empty($eventDetail->date)) {
            $response->date = convertDateWithFormatToISODate($eventDetail->date, "Y-m-d\TH:i:s");
        } else {
            $response->date = '';
        }

        if(property_exists($eventDetail, 'modified') && !empty($eventDetail->modified)) {
            $response->modified = convertDateWithFormatToISODate($eventDetail->modified, "Y-m-d\TH:i:s");
        } else {
            $response->modified = '';
        }

        $response->status = property_exists($eventDetail, 'status') ? $eventDetail->status : "";
        $response->link = property_exists($eventDetail, 'link') ? $eventDetail->link : "";

        $response->title = (property_exists($eventDetail, 'title') and property_exists($eventDetail->title, 'rendered')) ? html_entity_decode($eventDetail->title->rendered) : "";
        $response->content = (property_exists($eventDetail, 'content') and property_exists($eventDetail->content, 'rendered')) ? $eventDetail->content->rendered : "";
        $response->excerpt = (property_exists($eventDetail, 'excerpt') and property_exists($eventDetail->excerpt, 'rendered')) ? $eventDetail->excerpt->rendered : "";

        if(property_exists($eventDetail, 'acf') && property_exists($eventDetail->acf, 'startzeitpunkt') && !empty($eventDetail->acf->startzeitpunkt)) {
            if(validateDateIsUnixEpoch($eventDetail->acf->startzeitpunkt)) {
                $response->start_date = convertDateWithFormatToISODate($eventDetail->acf->startzeitpunkt, "U");
            } else {
                if(validateDateIsISOFormat($eventDetail->acf->startzeitpunkt)) {
                    $response->start_date = $eventDetail->acf->startzeitpunkt;
                } else if(validateDate($eventDetail->acf->startzeitpunkt, "Y-m-d H:i")) {
                    $response->start_date = convertDateWithFormatToISODate($eventDetail->acf->startzeitpunkt, "Y-m-d H:i");
                } else {
                    $response->start_date = convertDateWithFormatToISODate($eventDetail->acf->startzeitpunkt, "Y-m-d H:i:s");
                }
            }
        } else {
            $response->start_date = '';
        }

        if(property_exists($eventDetail, 'acf') && property_exists($eventDetail->acf, 'endzeitpunkt') && !empty($eventDetail->acf->endzeitpunkt)) {
            if(validateDateIsUnixEpoch($eventDetail->acf->endzeitpunkt)) {
                $response->end_date = convertDateWithFormatToISODate($eventDetail->acf->endzeitpunkt, "U");
            } else {
                if(validateDateIsISOFormat($eventDetail->acf->endzeitpunkt)) {
                    $response->end_date = $eventDetail->acf->endzeitpunkt;
                } else if(validateDate($eventDetail->acf->endzeitpunkt, "Y-m-d H:i")) {
                    $response->end_date = convertDateWithFormatToISODate($eventDetail->acf->endzeitpunkt, "Y-m-d H:i");
                } else {
                    $response->end_date = convertDateWithFormatToISODate($eventDetail->acf->endzeitpunkt, "Y-m-d H:i:s");
                }
            }
        } else {
            $response->end_date = $response->start_date;
        }

        if(property_exists($eventDetail, 'acf') && property_exists($eventDetail->acf, 'veranstaltungsort_name') && !empty($eventDetail->acf->veranstaltungsort_name)) {
            $response->location_name = $eventDetail->acf->veranstaltungsort_name;
        } else {
            $response->location_name = '';
        }

        if(property_exists($eventDetail, 'acf') && property_exists($eventDetail->acf, 'veranstaltungsort_strasse') && !empty($eventDetail->acf->veranstaltungsort_strasse)) {
            $response->location_street = $eventDetail->acf->veranstaltungsort_strasse;
        } else {
            $response->location_street = '';
        }

        if(property_exists($eventDetail, 'acf') && property_exists($eventDetail->acf, 'veranstaltungsort_plz') && !empty($eventDetail->acf->veranstaltungsort_plz)) {
            $response->location_zip = $eventDetail->acf->veranstaltungsort_plz;
        } else {
            $response->location_zip = '';
        }

        if(property_exists($eventDetail, 'acf') && property_exists($eventDetail->acf, 'veranstaltungsort_stadt') && !empty($eventDetail->acf->veranstaltungsort_stadt)) {
            $response->location_city = $eventDetail->acf->veranstaltungsort_stadt;
        } else {
            $response->location_city = '';
        }

        $response->featured_image = (property_exists($eventDetail, 'featured_image_url') and $eventDetail->featured_image_url !== false && !empty($eventDetail->featured_image_url)) ? $eventDetail->featured_image_url : getCardNameImageUrl($request->input('card_name'));
        $response->origin = 'intern';

        if(property_exists($eventDetail, 'acf') && property_exists($eventDetail->acf, 'type') && !empty($eventDetail->acf->type)) {
            $response->type = $eventDetail->acf->type;
            if(property_exists($eventDetail->acf, 'externe_url') && !empty($eventDetail->acf->externe_url)) {
                $response->external_url = $eventDetail->acf->externe_url;
            } else {
                $response->external_url = null;
                $response->type = 'internal';
            }
        } else {
            $response->type = 'internal';
            $response->external_url = null;
        }

        if(property_exists($eventDetail, 'acf') && property_exists($eventDetail->acf, 'price') && !empty($eventDetail->acf->price)) {
            $response->price = $eventDetail->acf->price;
        } else {
            $response->price = '';
        }

        $response->categories = property_exists($eventDetail, 'categories') ? $eventDetail->categories : [];

        return response()->json( $response, 200 );
    });

    Route::get('/jobs', function (Request $request) {

        if(!$request->has('white_label_website_url') || $request->input('white_label_website_url') == NULL || $request->input('white_label_website_url') == '') {
            Log::error('[API]: /jobs: Es wurde keine white_label_website_url in der Session gefunden.');
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'no_url', 400 );
        }

        $getJobs = Http::withHeaders([
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
        ])->get($request->input('white_label_website_url') . 'wp-json/wp/v2/jobs?_fields=acf,id,date,date_gmt,modified,modified_gmt,status,link,title,featured_media,featured_image_url&per_page=100');

        if($getJobs->failed()) {
            Log::error('[API]: ' . $getJobs->body());
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Die Regionsdaten konnten nicht abgerufen werden. Bitte wenden Sie sich an den Support.', 'error_checking_region', 500 );
        }

        $jobs = json_decode($getJobs->body());
        if(!empty($jobs) && is_array($jobs) && count($jobs) > 0) {
            foreach ($jobs as $job) {
                $job->type = 'trolleymaker';
            }
        }


        $getTraineeJobs = Http::withHeaders([
                                     'Content-Type' => 'application/json; charset=utf-8',
                                     'Accept' => 'application/json',
                                 ])->get($request->input('white_label_website_url') . 'wp-json/wp/v2/traineejobs?_fields=acf,id,date,date_gmt,modified,modified_gmt,status,link,title,featured_media,featured_image_url&per_page=100');

        /*
        if($getTraineeJobs->failed()) {
            Log::error('[API]: ' . $getTraineeJobs->body());
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Die Regionsdaten konnten nicht abgerufen werden. Bitte wenden Sie sich an den Support.', 'error_checking_region', 500 );
        }
        */

        $traineeJobs = json_decode($getTraineeJobs->body());
        if(!empty($traineeJobs) && is_array($traineeJobs) && count($traineeJobs) > 0) {
            foreach ($traineeJobs as $traineeJob) {
                $traineeJob->type = 'trainee';
                $jobs[] = $traineeJob;
            }
        }


        $jobArray = array();

        foreach($jobs as $job) {
            $temp = new stdClass();

            $temp->id = property_exists($job, 'id') ? $job->id : -1;

            if(property_exists($job, 'date') && !empty($job->date)) {
                $temp->date = convertDateWithFormatToISODate($job->date, "Y-m-d\TH:i:s");
            } else {
                $temp->date = '';
            }

            if(property_exists($job, 'modified') && !empty($job->modified)) {
                $temp->modified = convertDateWithFormatToISODate($job->modified, "Y-m-d\TH:i:s");
            } else {
                $temp->modified = '';
            }

            $temp->status = property_exists($job, 'status') ? $job->status : "";
            $temp->link = property_exists($job, 'link') ? $job->link : "";

            $temp->title = (property_exists($job, 'title') and property_exists($job->title, 'rendered')) ?
                html_entity_decode($job->title->rendered) : "";
            $temp->link_to_job = (property_exists($job, 'acf') and property_exists($job->acf, 'link_zur_stellenanzeige')) ? $job->acf->link_zur_stellenanzeige : "";
            $temp->featured_image = (property_exists($job, 'featured_image_url') and $job->featured_image_url !== false && !empty($job->featured_image_url)) ? $job->featured_image_url : getCardNameImageUrl($request->input('card_name'));
            $temp->company_name = (property_exists($job, 'acf') and property_exists($job->acf, 'company_name')) ? $job->acf->company_name : "";

            $temp->type = property_exists($job, 'type') ? $job->type : "trolleymaker";

            array_push($jobArray, $temp);
        }

        if($request->input('region_name') === 'Landshut') {
            $getIdowaJobs = Http::withHeaders([
                                     'Content-Type' => 'application/json; charset=utf-8',
                                     'Accept' => 'application/json',
                                 ])->get('https://idowa-jobs.s3.eu-central-1.amazonaws.com/trolleymaker/landshut/jobs.json');

            if(!empty($getIdowaJobs)) {
                $idowaJobs = json_decode($getIdowaJobs->body());
                foreach ($idowaJobs as $key => $job) {
                    $tempJob = new stdClass();
                    $tempJob->id = $job->jobDetailId;
                    $tempJob->type = 'extern';
                    $tempJob->date = convertDateWithFormatToISODate($job->advertisementDate, "Y-m-d H:i:s");
                    $tempJob->modified = convertDateWithFormatToISODate($job->advertisementDate, "Y-m-d H:i:s");
                    $tempJob->status = 'publish';
                    $tempJob->link = $job->jobUrl;
                    $tempJob->link_to_job = $job->jobUrl;
                    $tempJob->title = $job->jobTitle;
                    $tempJob->company_name = $job->hiringOrganization;
                    $tempJob->featured_image = $job->companyLogoUrl ?? getCardNameImageUrl($request->input('card_name'));
                    $jobArray[] = $tempJob;
                }
            }
        }

        return response()->json( $jobArray, 200 );
    });

    Route::get('/news', function (Request $request) {

        if(!$request->has('white_label_website_url') || $request->input('white_label_website_url') == NULL || $request->input('white_label_website_url') == '') {
            Log::error('[API]: /news: Es wurde keine white_label_website_url in der Session gefunden.');
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'no_url', 400 );
        }

        $getNews = Http::withHeaders([
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
        ])->get($request->input('white_label_website_url') . 'wp-json/wp/v2/posts?_fields=acf,id,date,date_gmt,modified,modified_gmt,status,link,title,content,excerpt,featured_media,featured_image_url&per_page=100');

        if($getNews->failed()) {
            Log::error('[API]: ' . $getNews->body());
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Die Regionsdaten konnten nicht abgerufen werden. Bitte wenden Sie sich an den Support.', 'error_checking_region', 500 );
        }

        $allNews = json_decode($getNews->body());
        $newsArray = array();

        foreach($allNews as $post) {
            $temp = new stdClass();

            $temp->id = property_exists($post, 'id') ? $post->id : -1;

            if(property_exists($post, 'date') && !empty($post->date)) {
                $temp->date = convertDateWithFormatToISODate($post->date, "Y-m-d\TH:i:s");
            } else {
                $temp->date = '';
            }

            if(property_exists($post, 'modified') && !empty($post->modified)) {
                $temp->modified = convertDateWithFormatToISODate($post->modified, "Y-m-d\TH:i:s");
            } else {
                $temp->modified = '';
            }

            $temp->status = property_exists($post, 'status') ? $post->status : "";
            $temp->link = property_exists($post, 'link') ? $post->link : "";

            $temp->title = (property_exists($post, 'title') and property_exists($post->title, 'rendered')) ?
                html_entity_decode($post->title->rendered) : "";
            $temp->content = (property_exists($post, 'content') and property_exists($post->content, 'rendered')) ? $post->content->rendered : "";
            $temp->excerpt = (property_exists($post, 'excerpt') and property_exists($post->excerpt, 'rendered')) ? $post->excerpt->rendered : "";
            $temp->featured_image = (property_exists($post, 'featured_image_url') and $post->featured_image_url !== false && !empty($post->featured_image_url)) ? $post->featured_image_url : getCardNameImageUrl($request->input('card_name'));

            if(property_exists($post, 'acf') && is_object($post->acf) && property_exists($post->acf, 'type') && !empty
                ($post->acf->type)) {
                $temp->type = $post->acf->type;
                if(property_exists($post->acf, 'externe_url') && !empty($post->acf->externe_url)) {
                    $temp->external_url = $post->acf->externe_url;
                } else {
                    $temp->external_url = null;
                    $temp->type = 'internal';
                }
            } else {
                $temp->type = 'internal';
                $temp->external_url = null;
            }

            array_push($newsArray, $temp);
        }

        return response()->json( $newsArray, 200 );
    });

    Route::get('/news/{id}', function (Request $request, string $id) {

        if(!$request->has('white_label_website_url') || $request->input('white_label_website_url') == NULL || $request->input('white_label_website_url') == '') {
            Log::error('[API]: /news: Es wurde keine white_label_website_url in der Session gefunden.');
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'no_url', 400 );
        }

        $getNewsDetail = Http::withHeaders([
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
        ])->get($request->input('white_label_website_url') . 'wp-json/wp/v2/posts/' . $id . '?_fields=acf,id,date,date_gmt,modified,modified_gmt,status,link,title,content,excerpt,featured_media,featured_image_url');

        if($getNewsDetail->failed()) {
            Log::error('[API]: ' . $getNewsDetail->body());
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Die Regionsdaten konnten nicht abgerufen werden. Bitte wenden Sie sich an den Support.', 'error_checking_region', 500 );
        }

        $newsDetail = json_decode($getNewsDetail->body());

        $response = new stdClass();
        $response->id = property_exists($newsDetail, 'id') ? $newsDetail->id : -1;

        if(property_exists($newsDetail, 'date') && !empty($newsDetail->date)) {
            $response->date = convertDateWithFormatToISODate($newsDetail->date, "Y-m-d\TH:i:s");
        } else {
            $response->date = '';
        }

        if(property_exists($newsDetail, 'modified') && !empty($newsDetail->modified)) {
            $response->modified = convertDateWithFormatToISODate($newsDetail->modified, "Y-m-d\TH:i:s");
        } else {
            $response->modified = '';
        }

        $response->status = property_exists($newsDetail, 'status') ? $newsDetail->status : "";
        $response->link = property_exists($newsDetail, 'link') ? $newsDetail->link : "";

        $response->title = (property_exists($newsDetail, 'title') and property_exists($newsDetail->title, 'rendered')
        ) ? html_entity_decode($newsDetail->title->rendered) : "";
        $response->content = (property_exists($newsDetail, 'content') and property_exists($newsDetail->content, 'rendered')) ? $newsDetail->content->rendered : "";
        $response->excerpt = (property_exists($newsDetail, 'excerpt') and property_exists($newsDetail->excerpt, 'rendered')) ? $newsDetail->excerpt->rendered : "";
        $response->featured_image = (property_exists($newsDetail, 'featured_image_url') and $newsDetail->featured_image_url !== false && !empty($newsDetail->featured_image_url)) ? $newsDetail->featured_image_url : getCardNameImageUrl($request->input('card_name'));

        if(property_exists($newsDetail, 'acf') && is_object($newsDetail->acf) && property_exists($newsDetail->acf,'type') && !empty($newsDetail->acf->type)) {
            $response->type = $newsDetail->acf->type;
            if(property_exists($newsDetail->acf, 'externe_url') && !empty($newsDetail->acf->externe_url)) {
                $response->external_url = $newsDetail->acf->externe_url;
            } else {
                $response->external_url = null;
                $response->type = 'internal';
            }
        } else {
            $response->type = 'internal';
            $response->external_url = null;
        }

        return response()->json( $response, 200 );
    });

    Route::get('/featured', function (Request $request) {

        if(!$request->has('white_label_website_url') || $request->input('white_label_website_url') == NULL || $request->input('white_label_website_url') == '') {
            Log::error('[API]: /featured: Es wurde keine white_label_website_url in der Session gefunden.');
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'no_url', 400 );
        }

        $getFeaturedContent = Http::withHeaders([
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
        ])->get($request->input('white_label_website_url') . 'wp-json/slider/v1/all/');

        if($getFeaturedContent->failed()) {
            Log::error('[API]: ' . $getFeaturedContent->body());
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Die Regionsdaten konnten nicht abgerufen werden. Bitte wenden Sie sich an den Support.', 'error_checking_region', 500 );
        }

        $featuredContent = json_decode($getFeaturedContent->body());

        return response()->json( $featuredContent, 200 );
    });

    Route::post('/contact-form', function (Request $request) {

        $handleContactForm = _handleContactForm($request);
        if(isError($handleContactForm)) {
            return returnErrorObject($handleContactForm);
        }

        return response()->json( $handleContactForm, 200 );
    });

    //app gets list of georeferenzierung push notification
    Route::get('/push-notifications', function (Request $request) {

        $pushNotifications = getGWAllPushNotifications();
        if(isError($pushNotifications)) {
            return returnErrorObject($pushNotifications);
        }
        if($pushNotifications == NULL) {
            return returnNewErrorObject('Es wurden keine Push Nachrichten gefunden', 'no_pushNotifications', 500);
        }

        $pushNotificationsFiltered = array();
        foreach ($pushNotifications as $pushNotification) {
            $regionLowercased = strtolower($pushNotification->PNAUSWAHLREGION);
            if($regionLowercased == 'alle' || $regionLowercased == strtolower($request->input('region_name'))) {
                if($pushNotification->GWSTYPE != 'Georeferenzierung') {
                    continue;
                }
                if($pushNotification->GWSSTATUS != 'aktiviert') {
                    continue;
                }
                if($pushNotification->PNAUSWAHLVERSANDCLUSTER == 'Partner') {
                    continue;
                }
                $isLoggedIn = false;
                if($request->has('contact_person_gguid') && $request->input('contact_person_gguid') != NULL && !empty($request->input('contact_person_gguid'))) {
                    $isLoggedIn = true;
                }

                if($pushNotification->PNAUSWAHLKARTEN == 'registrierte' && $isLoggedIn == false) {
                    continue;
                }

                if($pushNotification->PNAUSWAHLKARTEN == 'nicht registrierte' && $isLoggedIn == true) {
                    continue;
                }

                $temp = new stdClass();
                $temp->latitude = $pushNotification->PNLATITUDE;
                $temp->longitude = $pushNotification->PNLONGITUDE;
                $temp->title = $pushNotification->PNTITEL;
                $temp->message = $pushNotification->PNNACHRICHT;
                array_push($pushNotificationsFiltered, $temp);
            }
        }

        return response()->json($pushNotificationsFiltered, 200);
    })->middleware(['AuthenticateWithApiOptional']);

    //app sends push notification registration
    Route::post('/push-notifications', function (Request $request) {

        if(!$request->has('type') || $request->input('type') == NULL || empty($request->input('type'))) {
            return returnNewErrorObject('Es wurde kein Type angegeben!', 'no_type', 400);
        }

        $typeLowercased = strtolower($request->input('type'));
        if($typeLowercased != 'kunde' && $typeLowercased != 'partner') {
            return returnNewErrorObject('Der angegebene Type ist ungültig!', 'invalid_type', 400);
        }

        if(!$request->has('deviceID') || $request->input('deviceID') == NULL || empty($request->input('deviceID'))) {
            return returnNewErrorObject('Es wurde keine deviceID angegeben!', 'no_deviceID', 400);
        }

        $values = _getSuggestedValuesForFirebaseClients(['FBREGION', 'FBNAMEDERKARTE']);

        if(!in_array($request->input('region_name'), $values['FBREGION'])) {
            //Log::error('Bei /push-notifications war der region_name in der Session / Middleware nicht im Feld FBREGION vorhanden');
            //sendErrorNotificationMail('Bei /push-notifications war der region_name in der Session / Middleware nicht im Feld FBREGION vorhanden');
            return returnNewErrorObject('Es ist ein Fehler aufgetreten, die Push-Benachrichtigungen konnten nicht angemeldet werden.', 'invalid_region_name', 400);
        }

        if(!in_array($request->input('card_name'), $values['FBNAMEDERKARTE'])) {
            Log::error('Bei /push-notifications war der card_name in der Session / Middleware nicht im Feld FBNAMEDERKARTE vorhanden. card_name: ' . $request->input("card_name") . ', values: ' . print_r($values["FBNAMEDERKARTE"], true));
            sendErrorNotificationMail('Bei /push-notifications war der card_name in der Session / Middleware nicht im Feld FBNAMEDERKARTE vorhanden');
            return returnNewErrorObject('Es ist ein Fehler aufgetreten, die Push-Benachrichtigungen konnten nicht angemeldet werden.', 'invalid_card_name', 400);
        }

        $firebaseClient = new stdClass();
        $firebaseClient->FBREGION = $request->input('region_name');
        $firebaseClient->FBNAMEDERKARTE = $request->input('card_name');
        $firebaseClient->FBFIREBASEID = $request->input('deviceID');
        switch ($typeLowercased) {
            case 'kunde':
                $firebaseClient->GWSTYPE = "Kunde";
                break;
            case 'kunden':
                $firebaseClient->GWSTYPE = "Kunde";
                break;
            case 'partner':
                $firebaseClient->GWSTYPE = "Partner";
            default:
                break;
        }

        $foundFirebaseClient = getGWFirebasenummerByFirebaseId($firebaseClient->FBFIREBASEID);
        $firebaseIDAlreadyExists = false;
        if(!$foundFirebaseClient || !property_exists($foundFirebaseClient, 'GGUID')) {
            $firebaseClientGGUID = _createFirebaseClientInGw($firebaseClient);
        } else {
            $firebaseClientGGUID = $foundFirebaseClient->GGUID;
            $firebaseIDAlreadyExists = true;
        }

        if(isError($firebaseClientGGUID)) {
            return returnNewErrorObject('Es ist ein Fehler aufgetreten, die Push-Benachrichtigungen konnten nicht angemeldet werden.', 'unknown_error', 500);
        }

        if(!$firebaseIDAlreadyExists && $request->has('contact_person_gguid') && $request->input('contact_person_gguid') != NULL && !empty($request->input('contact_person_gguid'))) {
            //app device is logged in customer or partner

            $addressGGUID = $request->input('contact_person_gguid');
            $addGwLink = addLinkFirebasenummernToCustomer($firebaseClientGGUID, $addressGGUID);

            if(isError($addGwLink)) {
                Log::error("Fehler beim Erstellen einer neuen Verknüpfung von FirebaseClient zu Adresse: " . print_r($addGwLink, true));
                sendErrorNotificationMail("Fehler beim Erstellen einer neuen Verknüpfung von FirebaseClient zu Adresse: " . print_r($addGwLink, true));
            }
        }

        return response()->json(new stdClass(), 200);
    })->middleware(AuthenticateWithApiOptional::class);

    //app can delete push notifications registration
    Route::delete('/push-notifications/{deviceID}', function (Request $request, string $deviceID) {
        $invalidDeviceIds = [];
        $invalidDeviceIds[] = $deviceID;
        $deletionResponse = deleteFirebaseClientsFoDeviceIDs($invalidDeviceIds);
        if(isError($deletionResponse)) {
            Log::error('DELETE /push-notifications/{deviceID}: Error deleting deviceIDs: ' . print_r
                ($deletionResponse, true));
            //fail silently
            //return returnErrorObject($deletionResponse);
        }

        return response()->json(new stdClass(), 200);
    });

    Route::get('/partners/{partnerGguid}/logo.png', function (Request $request, string $partnerGguid) {

        $gwGetLogo = Http::withHeaders([
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
            'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
        ])->get(env('GW_API_BASE') . '/type/address/' . $partnerGguid . '/image');

        if($gwGetLogo->successful()) {
            return response($gwGetLogo->body())->header('Content-Type', 'image/jpeg');
        }

        if($gwGetLogo->failed()) {
            return returnFallbackImage('partner', NULL, $partnerGguid);
        }
    })->withoutMiddleware([AuthenticateWithApiKey::class])->middleware(CheckIfApiKeyForRegion::class);

    Route::post('/receipt', function (Request $request) {

        if(!$request->has('amountCent')) {
            return returnNewErrorObject('Es wurde kein Betrag (amountCent) angegeben.', 'no_amountCent', 400);
        } else if(empty($request->input('amountCent')) || !is_numeric($request->input('amountCent')) ) {
            return returnNewErrorObject('Das angegebene Betrag ist ungültig. Er darf nur numerisch sein und muss als Cent-Betrag angegeben werden.', 'invalid_amountCent', 400);
        }

        $amount = number_format($request->input('amountCent') / 100, 2, ',', '.');
        $request->merge(['amount' => $amount]);

        $receiptPDF = handleGenerateReceipt($request);
        if(isError($receiptPDF)) {
            return returnErrorObject($receiptPDF);
        }

        return $receiptPDF->download('beleg.pdf');

    })->middleware(['AuthenticateWithApi', 'AuthenticateIsPartner']);

    Route::get('/weather/{language}/{latitude}/{longitude}', function (Request $request, $language, $latitude, $longitude) {

        $url = 'https://weatherkit.apple.com/api/v1/weather/' . $language . '/' . $latitude .'/' . $longitude . '?';
        $urlParameter = array();
        if($request->has('dataSets') && !empty($request->input('dataSets'))) {
            $urlParameter['dataSets'] = $request->input('dataSets');
        } else {
            $urlParameter['dataSets'] = 'currentWeather,forecastDaily,forecastHourly';
        }
        if($request->has('hourlyStart') && !empty($request->input('hourlyStart'))) {
            $urlParameter['hourlyStart'] = $request->input('hourlyStart');
        }
        if($request->has('hourlyEnd') && !empty($request->input('hourlyEnd'))) {
            $urlParameter['hourlyEnd'] = $request->input('hourlyEnd');
        }
        if($request->has('dailyStart') && !empty($request->input('dailyStart'))) {
            $urlParameter['dailyStart'] = $request->input('dailyStart');
        }
        /* temp bugfix of apple weatherkit api
        if($request->has('dailyEnd') && !empty($request->input('dailyEnd'))) {
            $urlParameter['dailyEnd'] = $request->input('dailyEnd');
        }
        */

        $url .= http_build_query($urlParameter);

        $getWeather = Http::withHeaders([
            'Authorization' => 'Bearer eyJhbGciOiJFUzI1NiIsImtpZCI6IlFWMlQyUTRURFUiLCJpZCI6IlhSOFVQREFTOFkuZGUudHJvbGxleW1ha2VyLndlYXRoZXJraXQifQ.eyJpc3MiOiJYUjhVUERBUzhZIiwiaWF0IjoxNjkzNTcyMTM3LCJleHAiOjMzMjUwNDgwOTM3LCJzdWIiOiJkZS50cm9sbGV5bWFrZXIud2VhdGhlcmtpdCJ9.JF3o1AM9IFIiySd-cURYqEWbBU8sQ-O7HWxrWBCZsgBlzl2gZRhIAIEHbMOt1s8m3Q-7gftjHIyWSXjM_S-Bwg',
        ])->get($url);

        if($getWeather->failed()) {
            Log::error('[API] Fehler beim Abrufen des Wetters: ' . $getWeather->body());
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Die Wetterdaten konnten nicht abgerufen werden. Bitte wenden Sie sich an den Support.', 'error_checking_weatherdata', 500 );
        }

        return response()->json( json_decode($getWeather->body()), 200 );
    });

    //end of block api/v1 apiv1
});


Route::prefix('api/v2')->middleware([RedirectIfApiRouteMissing::class, AuthenticateWithApiKey::class])->group(function () {

    Route::post('/partners/login', function (Request $request) {

        $partnerLogin = _handlePartnerLogin($request, true);

        if(isError($partnerLogin)) {
            return returnErrorObject($partnerLogin);
        }

        return response()->json( $partnerLogin, 200 );
    })->middleware(['AuthenticateWithAnonymousApiKey'])->withoutMiddleware(AuthenticateWithApiKey::class);

    Route::get('/app-settings', function (Request $request) {

        $company_data = getGwPersonalDataByGGUID($request->input('company_gguid'));
        if(isError($company_data)) {
            Log::error('In /app-settings trat ein error beim abrufen der Firma aus GW auf.');
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Die Firma wurde nicht gefunden. Bitte kontaktieren Sie den Support.', 'company_not_found', 400 );
        }
        if(!property_exists($company_data, 'GGUID')) {
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Die Firma wurde nicht gefunden. Bitte kontaktieren Sie den Support.', 'company_not_found', 400 );
        }
        if(!property_exists($company_data, 'NCREGION')) {
            Log::error('In /app-settings hat der Datensatz ' . $company_data->GGUID . ' keine NCREGION');
            sendErrorNotificationMail('In /app-settings hat der Datensatz ' . $company_data->GGUID . ' keine NCREGION');
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Die Firma wurde nicht gefunden. Bitte kontaktieren Sie den Support.', 'no_region', 400 );
        }

        $getRegionData = Http::withoutVerifying()->withHeaders([
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
        ])->get(config('services.wordpress.regions.endpoint') . '_fields=acf.logo_image_url,acf.primary_color,acf.container_color,acf.container_content_color,acf.secondary_highlight_color,acf.negative_balance_color,acf.dark_icons_in_app&region_name=' . $company_data->NCREGION);

        if($getRegionData->failed()) {
            Log::error($getRegionData->body());
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support', 'unknown_error', 500 );
        }

        $regionData = json_decode($getRegionData);
        if($regionData && count($regionData) > 1) {
            Log::error('Die Region konnte nicht eindeutig zugeordnet werden: ' . $company_data->NCREGION);
            return returnNewErrorObject('Es ist ein Fehler aufgetreten. Die Region konnte nicht eindeutig zugeordnet werden. Bitte wenden Sie sich an den Support.', 'region_not_unique', 400 );
        }

        $regionData = $regionData[0]->acf;

             $appSettings          = new stdClass();
             $appSettings->general = new stdClass();
             if (App::environment(['production', 'live'])) {
                 $appSettings->general->baseUrl = 'https://backend.mycity.cards';
             } else {
                 if (App::environment(['development', 'test'])) {
                     $appSettings->general->baseUrl = 'https://backend.trolleymaker-dev.de';
                 } else {
                     if (App::environment(['beta'])) {
                         $appSettings->general->baseUrl = 'https://beta-backend.mycity.cards';
                     } else {
                         $appSettings->general->baseUrl = 'https://backend.mycity.cards';
                     }
                 }
             }

             $regionId = api_getRegionId($company_data->NCREGION);
             if (isError($regionId)) {
                 return returnNewErrorObject('Die Regions-ID konnte nicht ermittelt werden: '. $company_data->NCREGION,
                                             'no_region', 400);
             }

             $fullValueOnly = api_getFullValueOnly($regionId);
             if (isError($fullValueOnly)) {
                 return returnNewErrorObject('Es konnte nicht ermittelt werden, ob nur der gesamte Betrag abgebucht werden kann.',
                                             'no_cardID', 400);
             }

             $regions = explode(',', env('REGIONS_API_KEYS'));
             $apiKey  = '';
             foreach ($regions as $region) {
                 $tempRegion = explode(':', $region);
                 if ($tempRegion[1] == $company_data->NCREGION) {
                     $apiKey = $tempRegion[0];
                 }
             }
             $appSettings->general->apiKey              = $apiKey;
             $appSettings->general->cardImageUrl        = getCardNameImageUrl($request->input('card_name'));
             $appSettings->general->logoImageUrl        = $regionData->logo_image_url;
             $appSettings->general->fullValueOnly       = $fullValueOnly;
             $appSettings->theme                        = new stdClass();
             $appSettings->theme->primary               = $regionData->primary_color;
             $appSettings->theme->container             = $regionData->container_color;
             $appSettings->theme->containerContent      = $regionData->container_content_color;
             $appSettings->theme->negativeBalance       = $regionData->negative_balance_color;
             $appSettings->theme->secondaryHighlight    = $regionData->secondary_highlight_color;
             $appSettings->theme->darkIcons             = $regionData->dark_icons_in_app;
             $appSettings->staticInfo                   = new stdClass();
             $appSettings->staticInfo->privacyUrl       = 'https://rechtliches.trolleymaker.com/datenschutzerklaerung-app.html';
             $appSettings->staticInfo->imprintUrl       = 'https://rechtliches.trolleymaker.com/impressum-app.html';
             $appSettings->staticInfo->partnerPortalUrl = 'https://mycity.cards/partner-login?region=' . $company_data->NCREGION;

        return response()->json( $appSettings, 200 );
    })->middleware(['AuthenticateWithAnonymousApiKey', 'AuthenticateWithApi', 'AuthenticateIsPartner'])->withoutMiddleware(AuthenticateWithApiKey::class);


    Route::post('/add-card', function (Request $request) {
        $returnFromHandle = _handleAddCard($request);
        if(isError($returnFromHandle)) {
            return returnErrorObject($returnFromHandle);
        }

        return response()->json( $returnFromHandle, 200 );
    })->middleware(['AuthenticateWithApi', 'AuthenticateIsCustomer']);

    Route::get('/partners/categories', function (Request $request) {
        $categories = _getSuggestedValuesForAddress(['CATEGORY']);

        if(isError($categories)){
            return returnErrorObject($categories);
        }

        $groupedCategories = [
            'Täglicher Bedarf' => ["categories" => [], 'icon' => "cart", "name" => "Täglicher Bedarf"],
            'Gastronomie' => ["categories" => [], 'icon' => "gastronomy", "name" => "Gastronomie"],
            'Haus und Garten' => ["categories" => [], 'icon' => "house", "name" => "Haus und Garten"],
            'Mode' => ["categories" => [], 'icon' => "fashion", "name" => "Mode"],
            'Freizeit' => ["categories" => [], 'icon' => "freetime", "name" => "Freizeit"],
            'Auto und Tanken' => ["categories" => [], 'icon' => "car", "name" => "Auto und Tanken"],
            'Körper und Kosmetik' => ["categories" => [], 'icon' => "cosmetic", "name" => "Körper und Kosmetik"],
            'Sonstiges' => ["categories" => [], 'icon' => "misc", "name" => "Sonstiges"],
        ];
        if(is_array($categories['CATEGORY'])  && array_key_exists('CATEGORY', $categories) && count($categories['CATEGORY']) > 0) {
            foreach ($categories['CATEGORY'] as $category) {
                switch (strtolower($category)) {
                    case 'supermarkt':
                    case 'tierbedarf':
                    case 'drogerie':
                    case 'lebensmittel':
                        $groupedCategories['Täglicher Bedarf']['categories'][] = $category;
                        break;
                    case 'gastronomie':
                        $groupedCategories['Gastronomie']['categories'][] = $category;
                        break;
                    case 'handwerk':
                    case 'elektronik & technik':
                    case 'einrichtung & wohnen':
                        $groupedCategories['Haus und Garten']['categories'][] = $category;
                        break;
                    case 'mode & schuhe & schmuck':
                        $groupedCategories['Mode']['categories'][] = $category;
                        break;
                    case 'blumen & geschenke':
                    case 'bücher & zeitung':
                    case 'bücher & zeitungen':
                    case 'schreibwaren & spielzeug':
                    case 'freizeit & urlaub':
                        $groupedCategories['Freizeit']['categories'][] = $category;
                        break;
                    case 'auto & tanken':
                        $groupedCategories['Auto und Tanken']['categories'][] = $category;
                        break;
                    case 'sport & gesundheit':
                    case 'friseur & kosmetik':
                        $groupedCategories['Körper und Kosmetik']['categories'][] = $category;
                        break;
                    case 'finanzen':
                    case 'dienstleistungen':
                    case 'dienstleistung':
                        $groupedCategories['Sonstiges']['categories'][] = $category;
                        break;
                    default:
                        $groupedCategories['Sonstiges']['categories'][] = $category;
                        break;
                }
            }

            return response()->json(array_values($groupedCategories), 200);
        }

        return response()->json([], 500);
    });


    Route::get('/games', function (Request $request) {

        $activeGames = getAllActiveGamesAndActionsForRegion($request->input('region_name'), false);
        if(isError($activeGames)) {
            Log::error('Error checking /games: ' . print_r($activeGames->errorMessage, true));
            return createErrorObject('Die aktuellen Spiele und Aktionen konnte nicht abgerufen werden.', 'error_active_games',
                500);
        }
        if(count($activeGames) <= 0) {
            return response()->json([], 200);
        }

        $response = [];
        foreach ($activeGames as $game) {
            if(property_exists($game, 'GWSTYPE')) {
                $tempGame = new stdClass();
                $tempGame->id = $game->GGUID;
                $type_lowercased = strtolower($game->GWSTYPE);
                if($type_lowercased  === 'spiele' || $type_lowercased === 'spiel') {
                    if(property_exists($game, 'TMSPIELTYP') && !empty($game->TMSPIELTYP)) {
                        $gametype_lowercased = strtolower($game->TMSPIELTYP);
                        if($gametype_lowercased == 'tombola' || $gametype_lowercased == 'entenrennen') {
                            $tempGame->game_type = 'lot';
                        } else if(str_contains($gametype_lowercased, 'bingo')) {
                            $tempGame->game_type = 'bingo';
                        }
                        $tempGame->game_type_original = $game->TMSPIELTYP;
                    }
                } else if($type_lowercased  === 'aktionen' || $type_lowercased === 'aktion') {
                    if(property_exists($game, 'TMAKTIONSTYP') && !empty($game->TMAKTIONSTYP)) {
                        $gametype_lowercased = strtolower($game->TMAKTIONSTYP);
                        if($gametype_lowercased == 'kalender') {
                            $tempGame->game_type = 'calendar';
                        }
                        $tempGame->game_type_original = $game->TMAKTIONSTYP;
                    }
                }
                $tempGame->type = $tempGame->game_type;
                $tempGame->title = property_exists($game, 'KEYWORD') ?
                    $game->KEYWORD : '';
                $tempGame->description = property_exists($game, 'TMBESCHREIBUNGSTEXT') ?
                    $game->TMBESCHREIBUNGSTEXT : '';

                if(property_exists($game, 'TMLINKZUTEILNAHMEBEDINGUNGEN')) {
                    $tempGame->link_terms_conditions = $game->TMLINKZUTEILNAHMEBEDINGUNGEN;
                }
                if(property_exists($game, 'GWSSTATUS')) {
                    $tempGame->status = $game->GWSSTATUS;
                }
                if(property_exists($game, 'TMDESIGN')) {
                    $tempGame->design = $game->TMDESIGN;
                    $tempGame->variation = $game->TMDESIGN;
                }
                if(property_exists($game, 'TMGUELTIGVON')) {
                    $tempGame->visible_start_date = $game->TMGUELTIGVON;
                }
                if(property_exists($game, 'TMGUELTIGBIS')) {
                    $tempGame->visible_end_date = $game->TMGUELTIGBIS;
                }
                if(property_exists($game, 'TMTERMINAUSLOSUNG')) {
                    $tempGame->draw_date = $game->TMTERMINAUSLOSUNG;
                }
                if(property_exists($game, 'TMSPIELZEITBEACHTEN') && $game->TMSPIELZEITBEACHTEN == true) {
                    if(property_exists($game, 'TMTMBEGINNSPIELZEIT')) {
                        $tempGame->play_start_date = $game->TMTMBEGINNSPIELZEIT;
                    }
                    if(property_exists($game, 'TMENDESPIELZEIT')) {
                        $tempGame->play_end_date = $game->TMENDESPIELZEIT;
                    }
                }
                array_push($response, $tempGame);
            }
        }

        return response()->json($response, 200);
    });


    Route::get('/games/advent-calendar', function (Request $request) {

        $gamesAndActions = getAllActiveGamesAndActionsForRegion($request->input('region_name'));

        if(isError($gamesAndActions)) {
            Log::error('GET /games/advent-calendar Error: ' . print_r($gamesAndActions, true));
            //fail silently
            return response()->json( new stdClass(), 500 );
        }

        if(count($gamesAndActions) <= 0) {
            return response()->json( new stdClass(), 200 );
        }

        $adventscalendarActionRunning = false;
        $adventscalendarGame = null;
        foreach ($gamesAndActions as $gamesAndAction) {
            if($gamesAndAction->GWSTYPE === 'Aktionen' && property_exists($gamesAndAction, 'TMAKTIONSTYP')) {
                if($gamesAndAction->TMAKTIONSTYP === 'Kalender' || $gamesAndAction->TMAKTIONSTYP === 'Adventskalender') {
                    $adventscalendarActionRunning = true;
                    $adventscalendarGame = $gamesAndAction;
                    break;
                }
            }
        }

        if(!$adventscalendarActionRunning) {
            return response()->json( new stdClass(), 200 );
        }

        $actionItems = getItemsForGamesAndActionsGguid($adventscalendarGame->GGUID);
        if(isError($actionItems)) {
            Log::error('GET /games/advent-calendar Error getting items (for GGUID ' . print_r
                ($adventscalendarGame->GGUID,true) . '): ' . print_r($actionItems, true));
            return returnErrorObject($actionItems);
        }

        $response = new stdClass();
        $response->doors = [];
        $response->calendarLayout = [];

        if(count($actionItems) > 0) {

            usort($actionItems, function ($a, $b) {
                if(!property_exists($a, 'TMKALENDERDATUM') && !property_exists($b, 'TMKALENDERDATUM')) {
                    return 0;
                }
                if(!property_exists($a, 'TMKALENDERDATUM')) {
                    return 1;
                }
                if(!property_exists($b, 'TMKALENDERDATUM')) {
                    return -1;
                }
                return strtotime($a->TMKALENDERDATUM) <=> strtotime($b->TMKALENDERDATUM);
            });

            for ($i=1; $i <= count($actionItems); $i++) {
                $doorIndex = strval($i);
                $index = $i - 1;
                $response->doors[$doorIndex] = new stdClass();
                $response->doors[$doorIndex]->title = '';
                $response->doors[$doorIndex]->description = '';
                $response->doors[$doorIndex]->additionalInformations = '';
                $response->doors[$doorIndex]->logoUrl = 'https://simpli-citycard.com/wp-content/uploads/simplicitycard-icon-sprizz_sonne.png';

                if(property_exists($actionItems[$index], 'TMTITEL')) {
                    $response->doors[$doorIndex]->title = $actionItems[$index]->TMTITEL;
                }
                if(property_exists($actionItems[$index], 'NOTES')) {
                    $response->doors[$doorIndex]->description = $actionItems[$index]->NOTES;
                }
                if(property_exists($actionItems[$index], 'TMADDITIONALTEXT')) {
                    $response->doors[$doorIndex]->additionalInformations = $actionItems[$index]->TMADDITIONALTEXT;
                }

                $imageDocument = getLinkedImageForSuaItem($actionItems[$index]->GGUID);
                if(!isError($imageDocument) && !empty($imageDocument)) {
                    if(is_array($imageDocument) && count($imageDocument) > 0) {
                        $imageDocument = $imageDocument[0];
                    }
                    if(property_exists($imageDocument, 'fields')) {
                        $response->doors[$doorIndex]->logoUrl = 'https://backend.mycity.cards/images/' .
                            $imageDocument->fields->GGUID . '/image.png';
                    }
                }
            }
        }

        $usedDoors = [];
        for ($i=0; $i < 24; $i++) {
            $tempCalendarLayout = new stdClass();
            $tempCalendarLayout->row = intdiv($i, 4) + 1;
            $tempCalendarLayout->column = ($i % 4) + 1;
            $doorNumber = 1;
            switch ($i+1) {
                case 1:
                    $doorNumber = 15;
                    break;
                case 2:
                    $doorNumber = 8;
                    break;
                case 3:
                    $doorNumber = 23;
                    break;
                case 4:
                    $doorNumber = 2;
                    break;
                case 5:
                    $doorNumber = 19;
                    break;
                case 6:
                    $doorNumber = 6;
                    break;
                case 7:
                    $doorNumber = 13;
                    break;
                case 8:
                    $doorNumber = 22;
                    break;
                case 9:
                    $doorNumber = 1;
                    break;
                case 10:
                    $doorNumber = 17;
                    break;
                case 11:
                    $doorNumber = 10;
                    break;
                case 12:
                    $doorNumber = 3;
                    break;
                case 13:
                    $doorNumber = 21;
                    break;
                case 14:
                    $doorNumber = 7;
                    break;
                case 15:
                    $doorNumber = 5;
                    break;
                case 16:
                    $doorNumber = 18;
                    break;
                case 17:
                    $doorNumber = 4;
                    break;
                case 18:
                    $doorNumber = 24;
                    break;
                case 19:
                    $doorNumber = 11;
                    break;
                case 20:
                    $doorNumber = 20;
                    break;
                case 21:
                    $doorNumber = 9;
                    break;
                case 22:
                    $doorNumber = 14;
                    break;
                case 23:
                    $doorNumber = 16;
                    break;
                case 24:
                    $doorNumber = 12;
                    break;
                default:
                    break;
            }
            $tempCalendarLayout->door = $doorNumber;
            $response->calendarLayout[] = $tempCalendarLayout;
        }

        return response()->json( $response, 200 );
    });

    Route::get('/games/bingo-cards', function (Request $request) {

        $gamesAndActions = getAllActiveGamesAndActionsForRegion($request->input('region_name'), false);
        if(isError($gamesAndActions)) {
            Log::error('GET /games/bingo-cards Error: ' . print_r($gamesAndActions, true));
            //fail silently
            return response()->json( [], 500 );
        }

        if(count($gamesAndActions) <= 0) {
            return response()->json( [], 200 );
        }

        $bingoGamesRunning = false;
        $bingoGame = null;
        foreach ($gamesAndActions as $gamesAndAction) {
            if($gamesAndAction->GWSTYPE === 'Spiele' && property_exists($gamesAndAction, 'TMSPIELTYP')) {
                if(str_contains($gamesAndAction->TMSPIELTYP, 'Bingo')) {
                    $bingoGamesRunning = true;
                    $bingoGame = $gamesAndAction;
                    break;
                }
            }
        }

        if(!$bingoGamesRunning) {
            return response()->json( new stdClass(), 200 );
        }

        $actionItems = getItemsForGamesAndActionsGguid($bingoGame->GGUID, $request->input('contact_person_gguid'));
        if(isError($actionItems)) {
            Log::error('GET /games/bingo-cards Error getting items (for GGUID ' . print_r
                ($bingoGame->GGUID,true) . '): ' . print_r($actionItems, true));
            return returnErrorObject($actionItems);
        }

        $response = [];

        if(count($actionItems) > 0) {
            foreach ($actionItems as $actionItem) {
                if(!property_exists($actionItem, 'GWSSTATUS') || (strtolower($actionItem->GWSSTATUS) != 'lagernd' &&
                        strtolower($actionItem->GWSSTATUS) != 'ausgegeben' && strtolower($actionItem->GWSSTATUS) != 'eingelöst')) {
                    continue;
                }
                $tempBingoCard = new stdClass();
                $tempBingoCard->number = strval($actionItem->GWAUTONUM);
                $tempBingoCard->fields = [];
                if(property_exists($actionItem, 'TMNUMMER') && !empty($actionItem->TMNUMMER)) {
                    $tempBingoCard->fields = explode(',', $actionItem->TMNUMMER);
                }
                $tempBingoCard->checkedFields = [];
                if(property_exists($actionItem, 'TMNUMMER2') && !empty($actionItem->TMNUMMER2)) {
                    $tempBingoCard->checkedFields = explode(',', $actionItem->TMNUMMER2);
                }
                $tempBingoCard->redeemed = true;
                if(property_exists($actionItem, 'GWSSTATUS') && (strtolower($actionItem->GWSSTATUS) == 'lagernd' ||
                        strtolower($actionItem->GWSSTATUS) == 'ausgegeben')) {
                    $tempBingoCard->redeemed = false;
                }
                $response[] = $tempBingoCard;
            }
        }

        return response()->json($response, 200);
    })->middleware(['AuthenticateWithApi', 'AuthenticateIsCustomer']);


    Route::post('/games/bingo-cards/field', function (Request $request) {

        if(!$request->has('number') || empty($request->input('number'))) {
            return returnNewErrorObject('Es wurde keine Bingo ID angegebenen.', 'no_number', 400);
        }
        if(!$request->has('field') || empty($request->input('field'))) {
            return returnNewErrorObject('Es wurde kein Bingo Feld angegebenen.', 'no_field', 400);
        }

        $bingoCardNumber = sanitize_text_field($request->input('number'));

        $bingoCard = getBingoCardByNumber($bingoCardNumber, $request->input('contact_person_gguid'));

        if(isError($bingoCard)) {
            return returnErrorObject($bingoCard);
        }

        if(strtolower($bingoCard->GWSSTATUS !== 'lagernd') && strtolower($bingoCard->GWSSTATUS) !== 'ausgegeben') {
            return returnNewErrorObject('Die Bingo-Karte hat einen ungültigen Status. Ggf. ist sie schon entwertet.',
                'invalid_status', 400);
        }


        $bingoGame = getLinkedGamesAndActionsForSuaItem($bingoCard->GGUID);

        if(isError($bingoGame) || !property_exists($bingoGame, 'GGUID')) {
            Log::error('Error in /games/bingo-cards/field by getLinkedGamesAndActionsForSuaItem. Linked object is error or has no gguid: ' . print_r($bingoGame, true));
            sendErrorNotificationMail('Error in /games/bingo-cards/field by getLinkedGamesAndActionsForSuaItem. Linked object is error or has no gguid: ' . print_r($bingoGame, true));
            return false;
        }

        if(property_exists($bingoGame, 'TMSPIELZEITBEACHTEN') && $bingoGame->TMSPIELZEITBEACHTEN == true) {
            if(!property_exists($bingoGame, 'TMTMBEGINNSPIELZEIT') || !property_exists($bingoGame, 'TMENDESPIELZEIT')) {
                Log::error('Ein Bingo Spiel ' . $bingoGame->GGUID . ' hat das Feld TMSPIELZEITBEACHTEN auf true gesetzt, aber die 
                Spielzeit Felder nicht ausgefüllt');
            }
            $now = _getGWNowDate();
            $nowDate = new DateTime($now);
            $startDate = new DateTime($bingoGame->TMTMBEGINNSPIELZEIT);
            $endDate = new DateTime($bingoGame->TMENDESPIELZEIT);
            if($nowDate < $startDate || $nowDate > $endDate) {
                return returnNewErrorObject('Die Bingo-Felder können erst angekreuzt werden, wenn das Spiel gestartet ist.', 'game_not_yet_started', 400);
            }
        }


        $fieldNumber = sanitize_text_field($request->input('field'));

        $fieldsToUpdate = new stdClass();
        if(!property_exists($bingoCard, 'TMNUMMER2') || empty($bingoCard->TMNUMMER2)) {
            $fieldsToUpdate->TMNUMMER2 = $fieldNumber;
        } else {
            $fieldsToUpdate->TMNUMMER2 = $bingoCard->TMNUMMER2 . ',' . $fieldNumber;
        }


        //TODO Check if already contains checked field number
        //TODO Check if sent field number is present in value field

        $fieldsToUpdate->GWSSTATUS = 'ausgegeben';
        $updateResponse = updateGwSuaitemsData($bingoCard->GGUID, $fieldsToUpdate);
        if(isError($updateResponse) || !$updateResponse) {
            Log::error('Error updating /games/bingo-cards/field : ' . print_r($updateResponse, true));
            return returnNewErrorObject('Das Bingo-Feld konnte nicht angekreuzt werden.', 'error_updating_bingocardfield',
                500);
        }

        return response()->json( new stdClass(), 200);
    })->middleware(['AuthenticateWithApi', 'AuthenticateIsCustomer']);

    Route::post('/games/bingo-cards/', function (Request $request) {

        if(!$request->has('number') || empty($request->input('number'))) {
            return returnNewErrorObject('Es wurde keine Bingo ID angegebenen.', 'no_number', 400);
        }
        if(!$request->has('type') || empty($request->input('type'))) {
            return returnNewErrorObject('Es wurde kein Bingo Typ angegebenen.', 'no_type', 400);
        }
        if($request->input('type') !== 'FULL' && $request->input('type') !== 'SMALL') {
            return returnNewErrorObject('Ungültiger Bingo Typ.', 'invalid_type', 400);
        }

        $bingoCard = getBingoCardByNumber($request->input('number'), $request->input('contact_person_gguid'));

        if(isError($bingoCard)) {
            return returnErrorObject($bingoCard);
        }

        if(strtolower($bingoCard->GWSSTATUS !== 'lagernd') && strtolower($bingoCard->GWSSTATUS) !== 'ausgegeben') {
            return returnNewErrorObject('Die Bingo-Karte hat einen ungültigen Status. Ggf. ist sie schon entwertet.');
        }

        $fieldsToUpdate = new stdClass();
        $fieldsToUpdate->GWSSTATUS = 'eingelöst';
        $fieldsToUpdate->TMADDITIONALTEXT = sanitize_text_field($request->input('type'));
        $updateResponse = updateGwSuaitemsData($bingoCard->GGUID, $fieldsToUpdate);
        if(isError($updateResponse) || !$updateResponse) {
            Log::error('Error updating /games/bingo-cards/ : ' . print_r($updateResponse, true));
            return returnNewErrorObject('Die Bingo-Karte konnte nicht entwertet werden.', 'error_updating_bingocard',
                500);
        }

        return response()->json( new stdClass(), 200);
    })->middleware(['AuthenticateWithApi', 'AuthenticateIsCustomer']);

    Route::get('/games/lots', function (Request $request) {

        $gamesAndActions = getAllActiveGamesAndActionsForRegion($request->input('region_name'));
        if(isError($gamesAndActions)) {
            Log::error('GET /games/lots Error: ' . print_r($gamesAndActions, true));
            //fail silently
            return response()->json( [], 500 );
        }

        if(count($gamesAndActions) <= 0) {
            return response()->json( [], 200 );
        }

        $lotsGameRunning = false;
        $lotsGame = null;
        foreach ($gamesAndActions as $gamesAndAction) {
            if($gamesAndAction->GWSTYPE === 'Spiele' && property_exists($gamesAndAction, 'TMSPIELTYP')) {
                if(str_contains($gamesAndAction->TMSPIELTYP, 'Tombola')) {
                    $lotsGameRunning = true;
                    $lotsGame = $gamesAndAction;
                    break;
                }
            }
        }

        if(!$lotsGameRunning) {
            return response()->json( new stdClass(), 200 );
        }

        $actionItems = getItemsForGamesAndActionsGguid($lotsGame->GGUID, $request->input('contact_person_gguid'));
        if(isError($actionItems)) {
            Log::error('GET /games/lots Error getting items (for GGUID ' . print_r
                ($lotsGame->GGUID,true) . '): ' . print_r($actionItems, true));
            return returnErrorObject($actionItems);
        }

        $response = [];

        if(count($actionItems) > 0) {
            foreach ($actionItems as $actionItem) {
                $tempLot = new stdClass();
                $tempLot->redeemed = true;
                if(property_exists($actionItem, 'GWSSTATUS')) {
                    $statusLowercased = strtolower($actionItem->GWSSTATUS);
                    if($statusLowercased == 'ausgegeben') {
                        $tempLot->redeemed = false;
                        $tempLot->status = 'active';
                    } else if($statusLowercased == 'eingelöst') {
                        $tempLot->status = 'win';
                    } else if($statusLowercased == 'verfallen' || $statusLowercased == 'deaktiviert') {
                        $tempLot->status = 'lose';
                    }
                }

                $tempLot->number = '';
                if(property_exists($actionItem, 'TMNUMMER') && !empty($actionItem->TMNUMMER)) {
                    $tempLot->number = (string)$actionItem->TMNUMMER;
                }
                $response[] = $tempLot;
            }
        }

        return response()->json($response, 200);
    })->middleware(['AuthenticateWithApi', 'AuthenticateIsCustomer']);


    Route::post('/games/lots', function (Request $request) {

        if(!$request->has('number') || empty($request->input('number'))) {
            return returnNewErrorObject('Es wurde keine Losnummer angegebenen.', 'no_number', 400);
        }

        $lotNumber = sanitize_text_field($request->input('number'));

        $lot = getLotByNumber($lotNumber, $request->input('contact_person_gguid'));

        if(isError($lot)) {
            return returnErrorObject($lot);
        }

        if(strtolower($lot->GWSSTATUS !== 'lagernd') && strtolower($lot->GWSSTATUS) !== 'ausgegeben') {
            return returnNewErrorObject('Das Los hat einen ungültigen Status. Ggf. ist es schon entwertet.');
        }

        $fieldNumber = sanitize_text_field($request->input('field'));

        $fieldsToUpdate = new stdClass();
        $fieldsToUpdate->GWSSTATUS = 'eingelöst';
        $updateResponse = updateGwSuaitemsData($lot->GGUID, $fieldsToUpdate);
        if(isError($updateResponse) || !$updateResponse) {
            Log::error('Error updating /games/lots : ' . print_r($updateResponse, true));
            return returnNewErrorObject('Das Los konnte nicht eingelöst werden.', 'error_updating_lot',
                500);
        }

        return response()->json( new stdClass(), 200);
    })->middleware(['AuthenticateWithApi', 'AuthenticateIsCustomer']);


    Route::get('/modules', function (Request $request) {

        $regionData = getRegionData($request->input('region_name'), $request->input('card_name'), ['acf.customer_app_modules']);
        if(isError($regionData) || !property_exists($regionData, 'acf')) {
            Log::error('Fehler bei /modules, die Regionsdaten konnte nicht abgerufen werden: ' . print_r($regionData));
            return returnNewErrorObject('Es ist ein Fehler bei aufgetreten, die Regionsdaten konnten nicht abgerufen werden. Wenn das Problem weiterhin besteht, kontaktieren Sie bitte den Support.', 'unknown_error', 500);
        }

        $regionData = $regionData->acf;

        if(!property_exists($regionData, 'customer_app_modules')) {
            Log::error('Für die Region ' . print_r($request->input('region_name'), true) . ' und CARD name' . print_r
                ($request->input('card_name'), true) . ' wurden noch keine aktiven Module festgelegt');
            return returnNewErrorObject('Es ist ein Fehler bei aufgetreten. Wenn das Problem weiterhin besteht, kontaktieren Sie bitte den Support.', 'unknown_error', 500);
        }

        $response = new stdClass();
        $response->modules = $regionData->customer_app_modules;

        $activeGames = getAllActiveGamesAndActionsForRegion($request->input('region_name'), false);
        if(isError($activeGames)) {
            Log::error('Error checking games in /modules: ' . print_r($activeGames->errorMessage, true));
            return response()->json($response, 200);
        }
        if(count($activeGames) <= 0) {
            return response()->json($response, 200);
        }

        foreach ($activeGames as $game) {
            if(property_exists($game, 'GGUID')) {
                $response->modules[] = 'game-' . $game->GGUID;
            }
            /*
            if(property_exists($game, 'GWSTYPE')) {
                $type_lowercased = strtolower($game->GWSTYPE);
                if($type_lowercased  === 'spiele' || $type_lowercased === 'spiel') {
                    if(property_exists($game, 'TMSPIELTYP') && !empty($game->TMSPIELTYP)) {
                        $gametype_lowercased = strtolower($game->TMSPIELTYP);
                        if($gametype_lowercased == 'tombola' || $gametype_lowercased == 'entenrennen') {
                            $response->modules[] = 'lot';
                        } else if(str_contains($gametype_lowercased, 'bingo')) {
                            $response->modules[] = 'bingo';
                        }
                    }
                } else if($type_lowercased  === 'aktionen' || $type_lowercased === 'aktion') {
                    if(property_exists($game, 'TMAKTIONSTYP') && !empty($game->TMAKTIONSTYP)) {
                        $gametype_lowercased = strtolower($game->TMAKTIONSTYP);
                        if($gametype_lowercased == 'kalender') {
                            $response->modules[] = 'advent_calendar';
                        }
                    }
                }
            }
            */
        }

        return response()->json($response, 200);
    });

    Route::get('/app-versions', function (Request $request) {

        $validator = Validator::make([
            'region'     => $request->input('os'),
        ], [
            'region'     => 'required|alpha_num',
        ]);

        if ($validator->fails()) {
            Log::error('Validation Error in /app-versions, os: ' . print_r($request->input("os"),true));
            return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'no_os',
                400);
        }

        $response = new stdClass();

        $regionData = getRegionData($request->input('region_name'), $request->input('card_name'), ['acf.force_version_ios', 'acf.force_version_android', 'acf.version_ios_app', 'acf.version_android_app']);
        if(isError($regionData) || !property_exists($regionData, 'acf')) {
            Log::error('Fehler bei /app-versions, die Regionsdaten konnte nicht abgerufen werden: ' . print_r
                ($regionData, true));
            return returnNewErrorObject('Es ist ein Fehler bei aufgetreten, die Regionsdaten konnten nicht abgerufen werden. Wenn das Problem weiterhin besteht, kontaktieren Sie bitte den Support.', 'unknown_error', 500);
        }

        if(!is_object($regionData->acf)) {
            Log::error('Fehler bei /app-versions, es wurden keine validen Felder zurückgegeben: ' . print_r
                ($regionData, true));
            return returnNewErrorObject('Es ist ein Fehler bei aufgetreten, die Regionsdaten konnten nicht abgerufen werden. Wenn das Problem weiterhin besteht, kontaktieren Sie bitte den Support.', 'unknown_error', 500);
        }
        $regionData = $regionData->acf;

        $osLowercased = strtolower($request->input('os'));
        if($osLowercased == 'ios') {
            $response->forceVersion = (property_exists($regionData, 'force_version_ios') && !empty
                ($regionData->force_version_ios) && is_string($regionData->force_version_ios)) ? $regionData->force_version_ios : '1.0.0';
            $response->version = (property_exists($regionData, 'version_ios_app') && !empty
                ($regionData->version_ios_app) && is_string($regionData->version_ios_app)) ? $regionData->version_ios_app : '1.0.0';
        } else if($osLowercased == 'android') {
            $response->forceVersion = (property_exists($regionData, 'force_version_android') && !empty
                ($regionData->force_version_android) && is_string($regionData->force_version_android)) ? $regionData->force_version_android : '1.0.0';
            $response->version = (property_exists($regionData, 'version_android_app') && !empty
                ($regionData->version_android_app) && is_string($regionData->version_android_app)) ? $regionData->version_android_app : '1.0.0';
        } else {
            Log::error('Validation Error in /app-versions, kein gültiges os: ' . print_r($osLowercased,true));
            return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'invalid_os',
                400);
        }

        return response()->json($response, 200);
    });

    Route::get('/current-datetime', function (Request $request) {
        $response = new stdClass();
        $response->date = date('Y-m-d');
        $response->dateTime = date('Y-m-d\TH:i:s');
        $response->dateFormattedDE = date('d.m.Y');
        $response->dateTimeFormattedDE = date('d.m.Y H:i:s');
        return response()->json($response, 200);
    });

    Route::get('/test-push-payload', function (Request $request) {

        if(!$request->has('firebaseid')) {
            return returnNewErrorObject('no Parameter firebaseid', 'no_firebaseid', 400);
        }

        $factory = (new Factory)->withServiceAccount(env('PATH_TO_FIREBASE_CREDENTIALS2'));
        $messaging = $factory->createMessaging();

        $firebaseIds = [$request->input('firebaseid')];

        $message = CloudMessage::new()
            ->withNotification(Notification::create('Payload Test Titel', 'Payload Test Nachricht'))
            ->withData(['title' => 'Payload Test Titel', 'body' => 'Payload Test Nachricht'])
            ->withDefaultSounds();


        $report = $messaging->sendMulticast($message, $firebaseIds);
        $jsonresponse = new stdClass();
        $jsonresponse->result = 'Successful sends: ' . $report->successes()->count() . ', Failed sends: ' .
            $report->failures()->count();
        Log::error(print_r($report->failures()->getItems(),true));
        return response()->json($jsonresponse);


    })->withoutMiddleware([AuthenticateWithApiKey::class])->middleware(CheckIfApiKeyForRegion::class);


    Route::get('/partners/me', function (Request $request) {

        $personal_data = getGwPersonalDataByGGUID($request->input('contact_person_gguid'));
        if(isError($personal_data)) {
            return returnErrorObject($personal_data);
        }

        $username = _checkIfUsernameLinkExists($personal_data->GGUID);
        if(isError($username)) {
            return returnErrorObject($username);
        }

        $response_to_send = [
            'id' => $personal_data->GGUID,
            'salutation' => $personal_data->ADDRESSTERM ?? null,
            'gender' => $personal_data->GWGENDER ?? null,
            'firstName'=> $personal_data->CHRISTIANNAME,
            'lastName'=> $personal_data->NAME,
            'email' => $username->TMNUTZERMAIL,
            'userRole' => $personal_data->TMPARTNERPORTALROLLE ?? 'User'
        ];

        return response()->json( $response_to_send, 200 );
    })->middleware(['AuthenticateWithApi', 'AuthenticateIsPartner']);

    Route::any('{catchall}', function() { })->where('catchall', '^.*$')->name('api.v1.missed');

    //end of block api/v2 apiv2
});

Route::prefix('api/v3')->middleware([RedirectIfApiRouteMissing::class, AuthenticateWithApiKey::class])->group(function () {

    Route::get('/partners/booking', function (Request $request) {
        $returnFromHandle = _handleGetBooking($request);
        if(isError($returnFromHandle)) {
            return returnErrorObject($returnFromHandle);
        }

        return response()->json( $returnFromHandle, 200 );
    })->middleware(['AuthenticateWithApi', 'AuthenticateIsPartner']);

    Route::get('/partners/bonus', function (Request $request) {
        $response = _handleGetBonus($request);
        if(isError($response)) {
            return returnErrorObject($response);
        }

        return response()->json( $response, 200 );
    })->middleware(['AuthenticateWithApi', 'AuthenticateIsPartner']);

    Route::post('/partners/login', function (Request $request) {

        $partnerLogin = _handlePartnerLogin($request, true);

        if(isError($partnerLogin)) {
            return returnErrorObject($partnerLogin);
        }

        return response()->json( $partnerLogin, 200 );
    })->middleware(['AuthenticateWithAnonymousApiKey'])->withoutMiddleware(AuthenticateWithApiKey::class);

    Route::post('/partners/correction-booking', function (Request $request) {
        $correctionBooking = _handleCorrectionBooking($request);

        if(isError($correctionBooking)) {
            return returnErrorObject($correctionBooking);
        }

        return response()->json( new stdClass(), 200 );
    })->middleware(['AuthenticateWithApi', 'AuthenticateIsPartner']);

    Route::put('/partners/bonus', function (Request $request) {

        $handle_set_bonus = handleSetBonus($request);

        if(isError($handle_set_bonus)) {
            return returnErrorObject($handle_set_bonus);
        }

        return response()->json( $handle_set_bonus, 200 );
    })->middleware(['AuthenticateWithApi', 'AuthenticateIsPartnerAdmin']);

    Route::get('/partners/categories', function (Request $request) {
        $categories = _getSuggestedValuesForAddress(['CATEGORY']);

        if(isError($categories)){
            return returnErrorObject($categories);
        }

        $groupedCategories = [
            'Täglicher Bedarf' => ["categories" => [], 'icon' => "cart", "name" => "Täglicher Bedarf"],
            'Gastronomie' => ["categories" => [], 'icon' => "gastronomy", "name" => "Gastronomie"],
            'Haus und Garten' => ["categories" => [], 'icon' => "house", "name" => "Haus und Garten"],
            'Mode' => ["categories" => [], 'icon' => "fashion", "name" => "Mode"],
            'Freizeit' => ["categories" => [], 'icon' => "freetime", "name" => "Freizeit"],
            'Auto und Tanken' => ["categories" => [], 'icon' => "car", "name" => "Auto und Tanken"],
            'Körper und Kosmetik' => ["categories" => [], 'icon' => "cosmetic", "name" => "Körper und Kosmetik"],
            'Sonstiges' => ["categories" => [], 'icon' => "misc", "name" => "Sonstiges"],
        ];
        if(is_array($categories['CATEGORY'])  && array_key_exists('CATEGORY', $categories) && count($categories['CATEGORY']) > 0) {
            foreach ($categories['CATEGORY'] as $category) {
                switch (strtolower($category)) {
                    case 'supermarkt':
                    case 'tierbedarf':
                    case 'drogerie':
                    case 'lebensmittel':
                        $groupedCategories['Täglicher Bedarf']['categories'][] = $category;
                        break;
                    case 'gastronomie':
                        $groupedCategories['Gastronomie']['categories'][] = $category;
                        break;
                    case 'handwerk':
                    case 'elektronik & technik':
                    case 'einrichtung & wohnen':
                        $groupedCategories['Haus und Garten']['categories'][] = $category;
                        break;
                    case 'mode & schuhe & schmuck':
                        $groupedCategories['Mode']['categories'][] = $category;
                        break;
                    case 'blumen & geschenke':
                    case 'bücher & zeitung':
                    case 'bücher & zeitungen':
                    case 'schreibwaren & spielzeug':
                    case 'freizeit & urlaub':
                        $groupedCategories['Freizeit']['categories'][] = $category;
                        break;
                    case 'auto & tanken':
                        $groupedCategories['Auto und Tanken']['categories'][] = $category;
                        break;
                    case 'sport & gesundheit':
                    case 'friseur & kosmetik':
                        $groupedCategories['Körper und Kosmetik']['categories'][] = $category;
                        break;
                    case 'finanzen':
                    case 'dienstleistungen':
                    case 'dienstleistung':
                        $groupedCategories['Sonstiges']['categories'][] = $category;
                        break;
                    default:
                        $groupedCategories['Sonstiges']['categories'][] = $category;
                        break;
                }
            }

            return response()->json(array_values($groupedCategories), 200);
        }

        return response()->json([], 500);
    });

    Route::get('/partners/me', function (Request $request) {

        $personal_data = getGwPersonalDataByGGUID($request->input('contact_person_gguid'));
        if(isError($personal_data)) {
            return returnErrorObject($personal_data);
        }

        $username = _checkIfUsernameLinkExists($personal_data->GGUID);
        if(isError($username)) {
            return returnErrorObject($username);
        }

        $response_to_send = [
            'id' => $personal_data->GGUID,
            'salutation' => $personal_data->ADDRESSTERM ?? null,
            'gender' => $personal_data->GWGENDER ?? null,
            'firstName'=> $personal_data->CHRISTIANNAME,
            'lastName'=> $personal_data->NAME,
            'email' => $username->TMNUTZERMAIL,
            'userRole' => $personal_data->TMPARTNERPORTALROLLE ?? 'User'
        ];

        return response()->json( $response_to_send, 200 );
    })->middleware(['AuthenticateWithApi', 'AuthenticateIsPartner']);

    Route::get('/partners/transactions', function (Request $request) {

        if($request->has('fromDate') && !empty($request->input('fromDate'))) {
            if(!validateDateIsISOFormat($request->input('fromDate'))) {
                return returnNewErrorObject('Ungültiges "von" Datum. Bitte wenden Sie sich an den Support.', 'invalid_fromDate', 400);
            }
        } else {
            $request->merge(['fromDate' => (new DateTime('today -2 day midnight', new DateTimeZone('Europe/Berlin')))->format('Y-m-d\TH:i:s')]);
        }

        if($request->has('toDate') && !empty($request->input('toDate'))) {
            if(!validateDateIsISOFormat($request->input('toDate'))) {
                return returnNewErrorObject('Ungültiges "bis" Datum. Bitte wenden Sie sich an den Support.', 'invalid_toDate', 400);
            }
        } else {
            $request->merge(['toDate' => (new DateTime('tomorrow -1 second', new DateTimeZone('Europe/Berlin')))->format('Y-m-d\TH:i:s')]);
        }

        $transactions = _handleGetPartnerTransactions($request);
        if(isError($transactions)) {
            return returnErrorObject($transactions);
        }

        return response()->json( $transactions, 200 );
    })->middleware(['AuthenticateWithApi', 'AuthenticateIsPartner']);

    Route::get('/partners/{partnerGguid}', function (Request $request, string $partnerGguid) {

        $company_data = getGwPersonalDataByGGUID($partnerGguid);
        if (isError($company_data)) {
            return returnErrorObject($company_data);
        }

        $response                  = new stdClass();
        $response->closedMonday    = property_exists($company_data, 'TMPARTNERDATENVOLLSTAENDIG')
            ? $company_data->TMPARTNERHATGESCHLOSSENMO : true;
        $response->closedTuesday   = property_exists($company_data, 'TMPARTNERHATGESCHLOSSENDI')
            ? $company_data->TMPARTNERHATGESCHLOSSENDI : true;
        $response->closedWednesday = property_exists($company_data, 'TMPARTNERHATGESCHLOSSENMI')
            ? $company_data->TMPARTNERHATGESCHLOSSENMI : true;
        $response->closedThursday  = property_exists($company_data, 'TMPARTNERHATGESCHLOSSENDO')
            ? $company_data->TMPARTNERHATGESCHLOSSENDO : true;
        $response->closedFriday    = property_exists($company_data, 'TMPARTNERHATGESCHLOSSENFR')
            ? $company_data->TMPARTNERHATGESCHLOSSENFR : true;
        $response->closedSaturday  = property_exists($company_data, 'TMPARTNERHATGESCHLOSSENSA')
            ? $company_data->TMPARTNERHATGESCHLOSSENSA : true;
        $response->closedSunday    = property_exists($company_data, 'TMPARTNERHATGESCHLOSSENSO')
            ? $company_data->TMPARTNERHATGESCHLOSSENSO : true;

        $openingHours      = new stdClass();
        $openingHours->mon = [];
        $openingHours->tue = [];
        $openingHours->wed = [];
        $openingHours->thu = [];
        $openingHours->fri = [];
        $openingHours->sat = [];
        $openingHours->sun = [];
        $openingHours->mon = [];
        if (property_exists($company_data, 'TMOEFFZEITMONTAG1VON') && !empty($company_data->TMOEFFZEITMONTAG1VON) && property_exists($company_data, 'TMOEFFZEITMONTAG1BIS') && !empty($company_data->TMOEFFZEITMONTAG1BIS)) {
            $temp        = new stdClass();
            $temp->start = property_exists($company_data, 'TMOEFFZEITMONTAG1VON') ? $company_data->TMOEFFZEITMONTAG1VON
                : '';
            $temp->end   = property_exists($company_data, 'TMOEFFZEITMONTAG1BIS') ? $company_data->TMOEFFZEITMONTAG1BIS
                : '';
            $openingHours->mon[] = $temp;
        }
        if (property_exists($company_data, 'TMOEFFZEITMONTAG2VON') && !empty($company_data->TMOEFFZEITMONTAG2VON) && property_exists($company_data, 'TMOEFFZEITMONTAG2BIS') && !empty($company_data->TMOEFFZEITMONTAG2BIS)) {
            $temp        = new stdClass();
            $temp->start = property_exists($company_data, 'TMOEFFZEITMONTAG2VON') ? $company_data->TMOEFFZEITMONTAG2VON
                : '';
            $temp->end   = property_exists($company_data, 'TMOEFFZEITMONTAG2BIS') ? $company_data->TMOEFFZEITMONTAG2BIS
                : '';
            $openingHours->mon[] = $temp;
        }
        if (property_exists($company_data, 'TMOEFFZEITDIENSTAG1VON') && !empty($company_data->TMOEFFZEITDIENSTAG1VON) && property_exists($company_data, 'TMOEFFZEITDIENSTAG1BIS') && !empty($company_data->TMOEFFZEITDIENSTAG1BIS)) {
            $temp        = new stdClass();
            $temp->start = property_exists($company_data, 'TMOEFFZEITDIENSTAG1VON')
                ? $company_data->TMOEFFZEITDIENSTAG1VON : '';
            $temp->end   = property_exists($company_data, 'TMOEFFZEITDIENSTAG1BIS')
                ? $company_data->TMOEFFZEITDIENSTAG1BIS : '';
            $openingHours->tue[] = $temp;
        }
        if (property_exists($company_data, 'TMOEFFZEITDIENSTAG2VON') && !empty($company_data->TMOEFFZEITDIENSTAG2VON) && property_exists($company_data, 'TMOEFFZEITDIENSTAG2BIS') && !empty($company_data->TMOEFFZEITDIENSTAG2BIS)) {
            $temp        = new stdClass();
            $temp->start = property_exists($company_data, 'TMOEFFZEITDIENSTAG2VON')
                ? $company_data->TMOEFFZEITDIENSTAG2VON : '';
            $temp->end   = property_exists($company_data, 'TMOEFFZEITDIENSTAG2BIS')
                ? $company_data->TMOEFFZEITDIENSTAG2BIS : '';
            $openingHours->tue[] = $temp;
        }
        if (property_exists($company_data, 'TMOEFFZEITMITTWOCH1VON') && !empty($company_data->TMOEFFZEITMITTWOCH1VON) && property_exists($company_data, 'TMOEFFZEITMITTWOCH1BIS') && !empty($company_data->TMOEFFZEITMITTWOCH1BIS)) {
            $temp        = new stdClass();
            $temp->start = property_exists($company_data, 'TMOEFFZEITMITTWOCH1VON')
                ? $company_data->TMOEFFZEITMITTWOCH1VON : '';
            $temp->end   = property_exists($company_data, 'TMOEFFZEITMITTWOCH1BIS')
                ? $company_data->TMOEFFZEITMITTWOCH1BIS : '';
            $openingHours->wed[] = $temp;
        }
        if (property_exists($company_data, 'TMOEFFZEITMITTWOCH2VON') && !empty($company_data->TMOEFFZEITMITTWOCH2VON) && property_exists($company_data, 'TMOEFFZEITMITTWOCH2BIS') && !empty($company_data->TMOEFFZEITMITTWOCH2BIS)) {
            $temp        = new stdClass();
            $temp->start = property_exists($company_data, 'TMOEFFZEITMITTWOCH2VON')
                ? $company_data->TMOEFFZEITMITTWOCH2VON : '';
            $temp->end   = property_exists($company_data, 'TMOEFFZEITMITTWOCH2BIS')
                ? $company_data->TMOEFFZEITMITTWOCH2BIS : '';
            $openingHours->wed[] = $temp;
        }
        if (property_exists($company_data, 'TMOEFFZEITDONNERSTAG1VON') && !empty($company_data->TMOEFFZEITDONNERSTAG1VON) && property_exists($company_data, 'TMOEFFZEITDONNERSTAG1BIS') && !empty($company_data->TMOEFFZEITDONNERSTAG1BIS)) {
            $temp        = new stdClass();
            $temp->start = property_exists($company_data, 'TMOEFFZEITDONNERSTAG1VON')
                ? $company_data->TMOEFFZEITDONNERSTAG1VON : '';
            $temp->end   = property_exists($company_data, 'TMOEFFZEITDONNERSTAG1BIS')
                ? $company_data->TMOEFFZEITDONNERSTAG1BIS : '';
            $openingHours->thu[] = $temp;
        }
        if (property_exists($company_data, 'TMOEFFZEITDONNERSTAG2VON') && !empty($company_data->TMOEFFZEITDONNERSTAG2VON) && property_exists($company_data, 'TMOEFFZEITDONNERSTAG2BIS') && !empty($company_data->TMOEFFZEITDONNERSTAG2BIS)) {
            $temp        = new stdClass();
            $temp->start = property_exists($company_data, 'TMOEFFZEITDONNERSTAG2VON')
                ? $company_data->TMOEFFZEITDONNERSTAG2VON : '';
            $temp->end   = property_exists($company_data, 'TMOEFFZEITDONNERSTAG2BIS')
                ? $company_data->TMOEFFZEITDONNERSTAG2BIS : '';
            $openingHours->thu[] = $temp;
        }
        if (property_exists($company_data, 'TMOEFFZEITFREITAG1VON') && !empty($company_data->TMOEFFZEITFREITAG1VON) && property_exists($company_data, 'TMOEFFZEITFREITAG1BIS') && !empty($company_data->TMOEFFZEITFREITAG1BIS)) {
            $temp        = new stdClass();
            $temp->start = property_exists($company_data, 'TMOEFFZEITFREITAG1VON')
                ? $company_data->TMOEFFZEITFREITAG1VON : '';
            $temp->end   = property_exists($company_data, 'TMOEFFZEITFREITAG1BIS')
                ? $company_data->TMOEFFZEITFREITAG1BIS : '';
            $openingHours->fri[] = $temp;
        }
        if (property_exists($company_data, 'TMOEFFZEITFREITAG2VON') && !empty($company_data->TMOEFFZEITFREITAG2VON) && property_exists($company_data, 'TMOEFFZEITFREITAG2BIS') && !empty($company_data->TMOEFFZEITFREITAG2BIS)) {
            $temp        = new stdClass();
            $temp->start = property_exists($company_data, 'TMOEFFZEITFREITAG2VON')
                ? $company_data->TMOEFFZEITFREITAG2VON : '';
            $temp->end   = property_exists($company_data, 'TMOEFFZEITFREITAG2BIS')
                ? $company_data->TMOEFFZEITFREITAG2BIS : '';
            $openingHours->fri[] = $temp;
        }
        if (property_exists($company_data, 'TMOEFFZEITSAMSTAG1VON') && !empty($company_data->TMOEFFZEITSAMSTAG1VON) && property_exists($company_data, 'TMOEFFZEITSAMSTAG1BIS') && !empty($company_data->TMOEFFZEITSAMSTAG1BIS)) {
            $temp        = new stdClass();
            $temp->start = property_exists($company_data, 'TMOEFFZEITSAMSTAG1VON')
                ? $company_data->TMOEFFZEITSAMSTAG1VON : '';
            $temp->end   = property_exists($company_data, 'TMOEFFZEITSAMSTAG1BIS')
                ? $company_data->TMOEFFZEITSAMSTAG1BIS : '';
            $openingHours->sat[] = $temp;
        }
        if (property_exists($company_data, 'TMOEFFZEITSAMSTAG2VON') && !empty($company_data->TMOEFFZEITSAMSTAG2VON) && property_exists($company_data, 'TMOEFFZEITSAMSTAG2BIS') && !empty($company_data->TMOEFFZEITSAMSTAG2BIS)) {
            $temp        = new stdClass();
            $temp->start = property_exists($company_data, 'TMOEFFZEITSAMSTAG2VON')
                ? $company_data->TMOEFFZEITSAMSTAG2VON : '';
            $temp->end   = property_exists($company_data, 'TMOEFFZEITSAMSTAG2BIS')
                ? $company_data->TMOEFFZEITSAMSTAG2BIS : '';
            $openingHours->sat[] = $temp;
        }
        if (property_exists($company_data, 'TMOEFFZEITSONNTAG1VON') && !empty($company_data->TMOEFFZEITSONNTAG1VON) && property_exists($company_data, 'TMOEFFZEITSONNTAG1BIS') && !empty($company_data->TMOEFFZEITSONNTAG1BIS)) {
            $temp        = new stdClass();
            $temp->start = property_exists($company_data, 'TMOEFFZEITSONNTAG1VON')
                ? $company_data->TMOEFFZEITSONNTAG1VON : '';
            $temp->end   = property_exists($company_data, 'TMOEFFZEITSONNTAG1BIS')
                ? $company_data->TMOEFFZEITSONNTAG1BIS : '';
            $openingHours->sun[] = $temp;
        }
        if (property_exists($company_data, 'TMOEFFZEITSONNTAG2VON') && !empty($company_data->TMOEFFZEITSONNTAG2VON) && property_exists($company_data, 'TMOEFFZEITSONNTAG2BIS') && !empty($company_data->TMOEFFZEITSONNTAG2BIS)) {
            $temp        = new stdClass();
            $temp->start = property_exists($company_data, 'TMOEFFZEITSONNTAG2VON')
                ? $company_data->TMOEFFZEITSONNTAG2VON : '';
            $temp->end   = property_exists($company_data, 'TMOEFFZEITSONNTAG2BIS')
                ? $company_data->TMOEFFZEITSONNTAG2BIS : '';
            $openingHours->sun[] = $temp;
        }

        $response->openingHours                      = $openingHours;
        $response->companyOpenHoursAdditionalInfo    = property_exists($company_data, 'TMINFOOEFFNUNGSZEIT')
            ? $company_data->TMINFOOEFFNUNGSZEIT : '';
        $response->companyOpenHoursOnlyByArrangement = property_exists($company_data, 'TMTERMINVEREINBARUNG')
            ? $company_data->TMTERMINVEREINBARUNG : false;

        $response->companyName = property_exists($company_data, 'COMPNAME2') ? $company_data->COMPNAME2 : "";
        $response->category    = property_exists($company_data, 'CATEGORY') ? $company_data->CATEGORY : "";
        $response->city        = property_exists($company_data, 'TOWN2') ? $company_data->TOWN2 : "";
        $response->street      = property_exists($company_data, 'STREET2') ? $company_data->STREET2 : "";
        $response->zip         = property_exists($company_data, 'ZIP2') ? $company_data->ZIP2 : "";
        $response->country     = property_exists($company_data, 'COUNTRY2') ? $company_data->COUNTRY2 : "";
        $response->phone       = property_exists($company_data, 'TMPHONEVEROEFFENTLICHUNG')
            ? $company_data->TMPHONEVEROEFFENTLICHUNG : "";
        $response->email       = property_exists($company_data, 'TMMAILVEROEFFENTLICHUNG')
            ? $company_data->TMMAILVEROEFFENTLICHUNG : "";
        if (property_exists($company_data, 'WWWFIELDSTR1')) {
            if (str_starts_with(strtolower($company_data->WWWFIELDSTR1), 'http')) {
                $response->website = $company_data->WWWFIELDSTR1;
            } else {
                $response->website = 'https://' . $company_data->WWWFIELDSTR1;
            }
        } else {
            $response->website = "";
        }
        $response->latitude           = property_exists($company_data, 'GWLATITUDE') ? $company_data->GWLATITUDE : 0;
        $response->longitude          = property_exists($company_data, 'GWLONGITUDE') ? $company_data->GWLONGITUDE : 0;
        $response->categories         = property_exists($company_data, 'CATEGORY')
            ? explode(', ', $company_data->CATEGORY)
            : [];
        $response->canAddVoucher      = property_exists($company_data, 'TMISTAUFLADESTELLE')
            ? $company_data->TMISTAUFLADESTELLE : false;
        $response->canRedeemVoucher   = property_exists($company_data, 'TMISTEINLOESESTELLE')
            ? $company_data->TMISTEINLOESESTELLE : false;
        $response->instagramUrl       = property_exists($company_data, 'TMURLINSTAGRAM') ? $company_data->TMURLINSTAGRAM
            : NULL;
        $response->facebookUrl        = property_exists($company_data, 'TMURLFACEBOOK') ? $company_data->TMURLFACEBOOK
            : NULL;
        $response->calendarBookingUrl = property_exists($company_data, 'TMURLKALENDERTERMINBUCHUNG') ?
            $company_data->TMURLKALENDERTERMINBUCHUNG : NULL;
        $response->infoText = property_exists($company_data, 'TMINFOTEXTGESCHAEFT') ? $company_data->TMINFOTEXTGESCHAEFT : null;

        $response->logoUrl = 'https://backend.mycity.cards/api/v1/partners/' . $company_data->GGUID . '/logo.png';

        $featured_images = getDocumentsForCompany($company_data->GGUID, ['titelbild', 'basis plus bilder', 'premium bilder'], ['jpg', 'jpeg', 'png'], 'empfangen');

        $gwBonusResponse = Http::withHeaders([
    'Content-Type' => 'application/json; charset=utf-8',
    'Accept' => 'application/json',
    'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
    'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
])->post(env('GW_API_BASE') . '/query', [
    "query" => "SELECT b.*, a.GGUID as ADDRESSGGUID FROM BONI b LINK_JOIN(linkattribute='TMBONUS') ADDRESS a WHERE a.GGUID = 0x" . $company_data->GGUID . " AND b.GWSSTATUS = 'aktiviert' ORDER BY GWSTYPE"
]);

        $response->anyBonusActive = false;
        $response->permanentBonusActive = false;
        $response->promotionalBonusActive = false;

        if ($gwBonusResponse->successful()) {
            if (count(json_decode($gwBonusResponse)) > 0) {
                $gwBonusData = json_decode($gwBonusResponse)[0]->rows;

                if (count($gwBonusData) > 0) {
                    foreach ($gwBonusData as $bonus) {
                        $response->anyBonusActive = true;
                        if (!property_exists($response, 'boni')) {
                            $response->boni = [];
                        }
                        $tempBoni = formatBoniObject($bonus);
                        array_push($response->boni, $tempBoni);
                        $response->anyBonusActive = true;
                        $bonusTypeLowercased = strtolower($bonus->GWSTYPE);
                        if($bonusTypeLowercased == 'aktionsbonus') {
                            $response->promotionalBonusActive = true;
                        } else if($bonusTypeLowercased == 'dauerbonus') {
                            $response->permanentBonusActive = true;
                        }
                    }
                }
            }
        }

        $response->featuredImageUrls = [];
        if (!is_array($featured_images) && property_exists($featured_images, 'errorMessage') && !empty($featured_images->errorMessage)) {
            return response()->json($featured_images, 500);
        } else {
            if (is_array($featured_images)) {
                if (count($featured_images) > 0) {
                    foreach ($featured_images as $image) {
                        if(property_exists($image, 'documentType') && !empty($image->documentType)) {
                            $documentTypeLowercased = strtolower($image->documentType);
                            if($documentTypeLowercased == 'titelbild') {
                                array_unshift($response->featuredImageUrls, 'https://backend.mycity.cards/api/v1/partners/' .
                                                                            $image->gguid . '/titelbild.jpg');
                            } else {
                                $response->featuredImageUrls[] = 'https://backend.mycity.cards/api/v1/partners/' .
                                    $image->gguid . '/titelbild.jpg';
                            }

                        }
                    }
                }
            }
        }

        return response()->json($response, 200);
    });

    Route::get('/games', function (Request $request) {

        $activeGames = getAllActiveGamesAndActionsForRegion($request->input('region_name'), false);
        if(isError($activeGames)) {
            Log::error('Error checking /games: ' . print_r($activeGames->errorMessage, true));
            return createErrorObject('Die aktuellen Spiele und Aktionen konnte nicht abgerufen werden.', 'error_active_games',
                                     500);
        }
        if(count($activeGames) <= 0) {
            return response()->json([], 200);
        }

        $response = [];
        foreach ($activeGames as $game) {
            if(property_exists($game, 'GWSTYPE')) {
                $tempGame = new stdClass();
                $tempGame->id = $game->GGUID;
                $type_lowercased = strtolower($game->GWSTYPE);
                if($type_lowercased  === 'spiele' || $type_lowercased === 'spiel') {
                    if(property_exists($game, 'TMSPIELTYP') && !empty($game->TMSPIELTYP)) {
                        $gametype_lowercased = strtolower($game->TMSPIELTYP);
                        if($gametype_lowercased == 'tombola' || $gametype_lowercased == 'entenrennen') {
                            $tempGame->type = 'lot';
                        } else if(str_contains($gametype_lowercased, 'bingo')) {
                            $tempGame->type = 'bingo';
                        }
                        $tempGame->game_type_original = $game->TMSPIELTYP;
                    }
                } else if($type_lowercased  === 'aktionen' || $type_lowercased === 'aktion') {
                    if(property_exists($game, 'TMAKTIONSTYP') && !empty($game->TMAKTIONSTYP)) {
                        $gametype_lowercased = strtolower($game->TMAKTIONSTYP);
                        if($gametype_lowercased == 'kalender') {
                            $tempGame->type = 'calendar';
                        }
                        $tempGame->game_type_original = $game->TMAKTIONSTYP;
                    }
                }

                $tempGame->title = property_exists($game, 'KEYWORD') ?
                    $game->KEYWORD : '';
                $tempGame->description = property_exists($game, 'TMBESCHREIBUNGSTEXT') ?
                    $game->TMBESCHREIBUNGSTEXT : '';

                if(property_exists($game, 'TMLINKZUTEILNAHMEBEDINGUNGEN')) {
                    $tempGame->link_terms_conditions = $game->TMLINKZUTEILNAHMEBEDINGUNGEN;
                }
                if(property_exists($game, 'GWSSTATUS')) {
                    $tempGame->status = $game->GWSSTATUS;
                }
                if(property_exists($game, 'TMDESIGN')) {
                    $tempGame->design = $game->TMDESIGN;
                    $tempGame->variation = $game->TMDESIGN;
                }
                if(property_exists($game, 'TMGUELTIGVON')) {
                    $tempGame->visible_start_date = $game->TMGUELTIGVON;
                }
                if(property_exists($game, 'TMGUELTIGBIS')) {
                    $tempGame->visible_end_date = $game->TMGUELTIGBIS;
                }
                if(property_exists($game, 'TMTERMINAUSLOSUNG')) {
                    $tempGame->draw_date = $game->TMTERMINAUSLOSUNG;
                }
                if(property_exists($game, 'TMSPIELZEITBEACHTEN') && $game->TMSPIELZEITBEACHTEN == true) {
                    if(property_exists($game, 'TMTMBEGINNSPIELZEIT')) {
                        $tempGame->play_start_date = $game->TMTMBEGINNSPIELZEIT;
                    }
                    if(property_exists($game, 'TMENDESPIELZEIT')) {
                        $tempGame->play_end_date = $game->TMENDESPIELZEIT;
                    }
                }
                array_push($response, $tempGame);
            }
        }

        return response()->json($response, 200);
    });

    Route::any('{catchall}', function() { })->where('catchall', '^.*$')->name('api.v2.missed');
});

Route::prefix('api/v4')->middleware([RedirectIfApiRouteMissing::class, AuthenticateWithApiKey::class])->group(function () {

    Route::any('{catchall}', function() { })->where('catchall', '^.*$')->name('api.v3.missed');
});

function _linkCardToAddress($cardGguid, $addressGguid, $linkedToAttribute) {

    if($cardGguid == NULL || empty($cardGguid)) {
        Log::error("Bei _linkCardToAddress wurde keine CardGGUID angegeben oder sie ist leer / NULL");
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500 );
    }

    if($addressGguid == NULL || empty($addressGguid)) {
        Log::error("Bei _linkCardToAddress wurde keine AddressGGUID angegeben oder sie ist leer / NULL");
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500 );
    }

    if($linkedToAttribute == NULL || empty($linkedToAttribute)) {
        Log::error("Bei _linkCardToAddress wurde keine linkedToAttribute angegeben oder sie ist leer / NULL");
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500 );
    }

    $addGwLink = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/type/KARTENVERWALTUNG/' . $cardGguid . '/dossier?gguid2=' . $addressGguid . '&attribute=' . $linkedToAttribute . '&object-type2=ADDRESS');

    if($addGwLink->failed()) {
        Log::error("Fehler beim Erstellen einer neuen Verknüpfung in _linkCardToAddress: " . $addGwLink->body());
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500 );
    }

    return true;
}

function _deleteLinkCardToAddress($cardGguid, $dossierGguid, $linkedToAttribute) {

    if($cardGguid == NULL || empty($cardGguid)) {
        Log::error("Bei _linkCardToAddress wurde keine CardGGUID angegeben oder sie ist leer / NULL");
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500 );
    }

    if($dossierGguid == NULL || empty($dossierGguid)) {
        Log::error("Bei _linkCardToAddress wurde keine AddressGGUID angegeben oder sie ist leer / NULL");
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500 );
    }

    if($linkedToAttribute == NULL || empty($linkedToAttribute)) {
        Log::error("Bei _linkCardToAddress wurde keine linkedToAttribute angegeben oder sie ist leer / NULL");
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500 );
    }

    $addGwLink = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->delete(env('GW_API_BASE') . '/type/KARTENVERWALTUNG/' . $cardGguid . '/dossier/' . $dossierGguid . '@TMKVWADRESSE');

    if($addGwLink->failed()) {
        Log::error("Fehler beim Erstellen einer neuen Verknüpfung in _linkCardToAddress: " . $addGwLink->body());
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500 );
    }

    return true;
}

function _checkIfUsernameLinkExists($addressGguid) {

    if($addressGguid == NULL || !$addressGguid || empty($addressGguid)) {
        Log::error('No addressGGUID in _checkIfUsernameLinkExists');
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
    }

    $checkGwLink = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->get(env('GW_API_BASE') . '/type/NUTZERNAMEN/full?linked-to=' . $addressGguid .'&linked-to-type=ADDRESS&linked-to-attributes=TMNUTZERNAME');

    if($checkGwLink->failed()) {
        Log::error("Fehler beim Abrufen der Verknüpfungen in _checkIfUsernameLinkExists: " . $checkGwLink->body());
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500 );
    }

    Log::debug(print_r($checkGwLink->body(), true));
    $usernameLink = json_decode($checkGwLink);

    if(!$usernameLink || count($usernameLink) === 0) {
        return new stdClass();
    } else {
        if(count($usernameLink) > 1) {
            Log::error("Fehler beim Abrufen von _checkIfUsernameLinkExists (" . $addressGguid . "): Es wurden mehrere Verknüpfungen gefunden: " . print_r($checkGwLink->body(), true));
            return createErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
        }
    }

    return $usernameLink[0]->fields;
}

function addLinkSuaItemToGamesAndAction($suaitemGguid, $gameAndActionGguid) {
    return _addLink('SUAITEMS', $suaitemGguid, $gameAndActionGguid, 'SPIELEUNDAKTIONEN', 'SUAGRP2SUAITEM');
}
function addLinkSuaItemToCustomer($suaitemGguid, $addressGguid) {
    return _addLink('SUAITEMS', $suaitemGguid, $addressGguid, 'ADDRESS', 'ITEM2KUNDE');
}
function addLinkSuaItemToPartner($suaitemGguid, $addressGguid) {
    return _addLink('SUAITEMS', $suaitemGguid, $addressGguid, 'ADDRESS', 'ITEM2HERSTELLER');
}

function addLinkFirebasenummernToCustomer($firebasenummerGguid, $addressGguid) {
    return _addLink('FIREBASENUMMERN', $firebasenummerGguid, $addressGguid, 'ADDRESS', 'TMFIREBASEKUNDE');
}

function _addLink($objectType, $firstGguid, $secondGguid, $secondObjectType, $linkedToAttribute) {

    if($objectType == NULL || empty($objectType)) {
        Log::error("Bei _addLink wurde keine objectType angegeben oder sie ist leer / NULL");
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500 );
    }

    if($firstGguid == NULL || empty($firstGguid)) {
        Log::error("Bei _addLink wurde keine firstGguid angegeben oder sie ist leer / NULL");
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500 );
    }

    if($secondGguid == NULL || empty($secondGguid)) {
        Log::error("Bei _addLink wurde keine secondGguid angegeben oder sie ist leer / NULL");
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500 );
    }

    $addGwLink = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/type/' . $objectType . '/' . $firstGguid .
    '/dossier?gguid2=' . $secondGguid . '&attribute=' . $linkedToAttribute . '&object-type2=' . $secondObjectType);

    if($addGwLink->failed()) {
        Log::error("Fehler beim Erstellen einer neuen Verknüpfung in _addLink: " . $addGwLink->body());
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500 );
    }

    return true;
}

function _getGwCompanyDataSet($addressGguid)
{

        if ($addressGguid == NULL || !$addressGguid || empty($addressGguid)) {
                Log::error('No addressGGUID in _checkIfUsernameLinkExists');

                return createErrorObject(
                    'Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.',
                    'unknown_error',
                    500);
        }

        $checkGwLink = Http::withHeaders(
            [
                'Content-Type'      => 'application/json; charset=utf-8',
                'Accept'            => 'application/json',
                'Authorization'     => 'Basic ' . env("GW_AUTHORIZATION"),
                'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA==',
            ])->get(
            env('GW_API_BASE') . '/type/ADDRESS/full?linked-to=' . $addressGguid . '&linked-to-type=NUTZERNAMEN&linked-to-attributes=TMNUTZERNAME');

        $usernameLink      = json_decode($checkGwLink);
        $partnerFields     = [];
        $employerFields    = [];
        $contractorFields  = [];
        $interessentFields = [];

        foreach ($usernameLink as $link) {
                $fields = $link->fields ?? NULL;

                if ($fields !== NULL) {
                        if (isset($fields->TMMODULEPARTNER) && str_contains($fields->TMMODULEPARTNER, 'GutscheinCARD')) {
                                $partnerFields[] = $fields;

                        }
                        if (isset($fields->GWSTYPE) && str_contains($fields->GWSTYPE, 'Interessent')) {
                                $interessentFields[] = $fields;
                        }
                        if (isset($fields->TMMODULEPARTNER) && str_contains($fields->TMMODULEPARTNER, 'MitarbeiterCARD')) {
                                $employerFields[] = $fields;
                        }
                        if (isset($fields->TMARTDERPARTNERSCHAFT) && str_contains($fields->TMARTDERPARTNERSCHAFT, 'Auftraggeber')) {
                                $contractorFields[] = $fields;
                        }
                }
        }

        $result = [];

        if (!empty($partnerFields)) {
                $result['partnerFields'] = $partnerFields;
        }

        if (!empty($employerFields)) {
                $result['employerFields'] = $employerFields;
        }

        if (!empty($contractorFields)) {
                $result['contractorFields'] = $contractorFields;
        }
        if (!empty($interessentFields)) {
                $result['interessentFields'] = $interessentFields;
        }

        return $result;
}

function _getAddressForUsername($usernameGguid) {

    if($usernameGguid == NULL || !$usernameGguid || empty($usernameGguid)) {
        Log::error('No usernameGguid in _getAddressForUsername');
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
    }

    $checkGwLink = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->get(env('GW_API_BASE') . '/type/ADDRESS/full?linked-to=' . $usernameGguid .'&linked-to-type=NUTZERNAMEN&linked-to-attributes=TMNUTZERNAME');

    if($checkGwLink->failed()) {
        Log::error("Fehler beim Abrufen der Verknüpfungen in _getAddressForUsername: " . $checkGwLink->body());
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500 );
    }

    $addressLink = json_decode($checkGwLink);

    if(!$addressLink || count($addressLink) === 0) {
        return NULL;
    } else {
        if(count($addressLink) > 1) {
            Log::error("Fehler beim Abrufen von _getAddressForUsername (" . $usernameGguid . "): Es wurden mehrere Verknüpfungen gefunden: " . print_r($checkGwLink->body(), true));
            return createErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
        }
    }

    return $addressLink[0]->fields;
}

function getPasswordRecordForUsernameGGUID($usernameGguid) {

    $checkGwLink = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->get(env('GW_API_BASE') . '/type/PASSWORDS/full?linked-to=' . $usernameGguid .'&linked-to-type=NUTZERNAMEN&linked-to-attributes=TMPASSWORT');

    if($checkGwLink->failed()) {
        Log::error("Fehler beim Abrufen der Verknüpfungen in getPasswordRecordForUsernameGGUID: " . $checkGwLink->body());
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500 );
    }

    $passwordLink = json_decode($checkGwLink);
    return $passwordLink;
}

function _checkPasswordForUsernameGGUID($usernameGguid, $passwordToCheck) {

    $passwordLink = getPasswordRecordForUsernameGGUID($usernameGguid);

    if($passwordLink == NULL || !$passwordLink || count($passwordLink) === 0) {
        Log::error("Fehler beim Abrufen von _checkPasswordForUsernameGGUID (" . $usernameGguid . "): Es wurde keine Verknüpfungen gefunden: " . print_r($passwordLink, true));
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
    } else {
        if(count($passwordLink) > 1) {
            Log::error("Fehler beim Abrufen von _checkPasswordForUsernameGGUID (" . $usernameGguid . "): Es wurden mehrere Verknüpfungen gefunden: " . print_r($passwordLink->body(), true));
            return createErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
        }
    }

    $fields = $passwordLink[0]->fields;

    if (Hash::check($passwordToCheck, $fields->TMPW)) {
        return true;
    } else {
        return false;
    }
}


function createGwUsernameAndPassword($addressGguid, $userEmail, $clear_password, $lastLoginTimestamp = NULL, $userActive = 1, $lastUpdateTimestamp = NULL, $alternativeUsername = NULL) {

    if($lastUpdateTimestamp == NULL) {
        $lastUpdateTimestamp = _getGWNowDate();
    }

    if($lastLoginTimestamp == NULL) {
        $lastLoginTimestamp = _getGWNowDate();
    }

    $createUsernameResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/type/NUTZERNAMEN', [
        'fields' => [
            'TMNUTZERMAIL' => $userEmail,
            'TMLETZTERLOGIN' => $lastLoginTimestamp,
            'TMNUTZERAKTIV' => $userActive,
            'TMNUTZERNAME' => $alternativeUsername
        ]
    ]);

    if($createUsernameResponse->failed()) {
        Log::error("Fehler beim Anlegen des Usernames createUsernamePasswordResponse: " . print_r($createUsernameResponse->body(), true));
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support', 'unknown_error', 500);
    }

    if($createUsernameResponse->header('Location') == NULL || $createUsernameResponse->header('Location') == '') {
        Log::error("Fehler bei beim Anlegen des Usernames createUsernamePasswordResponse, Location Header für GGUID nicht vorhanden: " . print_r($createUsernameResponse->body(), true));
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support', 'unknown_error', 500);
    } else {
        $location_splitted = explode("/", $createUsernameResponse->header('Location'));
        $usernameGguid = end($location_splitted);
    }


    $addUsernameAddressGwLink = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/type/ADDRESS/' . $addressGguid . '/dossier?gguid2=' . $usernameGguid . '&attribute=TMNUTZERNAME&object-type2=NUTZERNAMEN');

    if($addUsernameAddressGwLink->failed()) {
        Log::error("Fehler beim Erstellen einer neuen Verknüpfung in createGwUsernamePassword: " . print_r($addUsernameAddressGwLink->body(), true));
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500 );
    }


    $hashedPassword = Hash::make($clear_password);

    $passwordGguid = createGwPasswordForUsername($usernameGguid, $hashedPassword, $lastUpdateTimestamp);
    if(isError($passwordGguid)) {
        return $passwordGguid;
    }

    $response = new stdClass();
    $response->usernameGguid = $usernameGguid;
    $response->passwordGguid = $passwordGguid;

    return $response;
}

function createGwPasswordForUsername($usernameGguid, $hashedPassword, $lastUpdateTimestamp) {

    $createPasswordResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/type/PASSWORDS', [
        'fields' => [
            'TMAENDERUNGDATE' => $lastUpdateTimestamp,
            'TMPW' => $hashedPassword
        ]
    ]);

    if($createPasswordResponse->failed()) {
        Log::error("Fehler beim Anlegen des Passworts in createGwPasswordForUsername: " . print_r($createPasswordResponse->body(), true));
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support', 'unknown_error', 500);
    }

    if($createPasswordResponse->header('Location') == NULL || $createPasswordResponse->header('Location') == '') {
        Log::error("Fehler in createGwPasswordForUsername, Location Header für GGUID nicht vorhanden: " . print_r($createPasswordResponse->body(), true));
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support', 'unknown_error', 500);
    } else {
        $location_splitted = explode("/", $createPasswordResponse->header('Location'));
        $passwordGguid = end($location_splitted);
    }


    $addUsernamePasswordGwLink = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/type/NUTZERNAMEN/' . $usernameGguid . '/dossier?gguid2=' . $passwordGguid . '&attribute=TMPASSWORT&object-type2=PASSWORDS');

    if($addUsernamePasswordGwLink->failed()) {
        Log::error("Fehler beim Erstellen einer neuen Verknüpfung in createGwPasswordForUsername: " . print_r($addUsernamePasswordGwLink->body(), true));
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
    }

    return $passwordGguid;
}

function _handleUpdateCustomerUserData($request) {

    if(!$request->input('gender')) {
        return createErrorObject('Es wurde kein Geschlecht angegeben', 'no_gender', 400 );
    }

    if(!$request->input('firstName')) {
        return createErrorObject('Es wurde kein Vorname angegeben', 'no_firstName', 400 );
    }

    if(!$request->input('lastName')) {
        return createErrorObject('Es wurde kein Nachname angegeben', 'no_lastName', 400 );
    }

    if(!$request->input('street')) {
        return createErrorObject('Es wurde keine Straße und Hausnummer angegeben', 'no_street', 400 );
    }

    if(!$request->input('zip')) {
        return createErrorObject('Es wurde keine Postleitzahl angegeben', 'no_zip', 400 );
    }

    if(!$request->input('city')) {
        return createErrorObject('Es wurde kein Ort angegeben', 'no_city', 400 );
    }

    if(!$request->input('country')) {
        return createErrorObject('Es wurde kein Land angegeben', 'no_country', 400 );
    }

    if(!$request->input('birthdate')) {
        return createErrorObject('Es wurde kein Geburtsdatum angegeben', 'no_birthdate', 400 );
    }

    if(preg_match('/\d/', $request->input('firstName')) || strlen($request->input('firstName')) < 2) {
        return createErrorObject('Der Vorname ist ungültig.', 'invalid_firstName', 400 );
    }
    if(preg_match('/\d/', $request->input('lastName')) || strlen($request->input('lastName')) < 2) {
        return createErrorObject('Das Nachname ist ungültig', 'invalid_lastName', 400 );
    }
    if(!preg_match('/\d/', $request->input('street'))) {
        return createErrorObject('Es wurde keine Hausnummer angegeben', 'invalid_street', 400 );
    }
    if(strlen($request->input('street')) < 2) {
        return createErrorObject('Die Straße muss mindestens zwei Zeichen lang sein', 'invalid_street', 400 );
    }


    $userInputData = new stdClass();

    if(strlen($request->input('zip')) == 4 || strlen($request->input('zip')) == 5) {
        $userInputData->zip = $request->input('zip');
    } else {
        return createErrorObject('Die Postleitzahl darf nur aus 4 oder 5 Zahlen bestehen', 'invalid_zip', 400 );
    }

    if(validateDateIsISOFormatWithoutTime($request->input('birthdate'))) {
        $userInputData->birthdate = $request->input('birthdate');
    } else if(validateDateIsISOFormat($request->input('birthdate'))) {
        $userInputData->birthdate = convertDateWithFormatToISODateWithoutTime($request->input('birthdate'), 'Y-m-d\TH:i:s');

    } else {
        return createErrorObject('Das Geburtsdatum ist ungültig / in einem ungültigen Format.', 'invalid_birthdate', 400);
    }
    $userInputData->birthdateFormattedDE = convertISODateToGermanDate($userInputData->birthdate);

    $userInputData->gender = $request->input('gender');
    if(strtolower($userInputData->gender) != 'weiblich' && strtolower($userInputData->gender) != 'männlich' && strtolower($userInputData->gender) != 'divers' && strtolower($userInputData->gender) != 'sonstige') {
        return createErrorObject('Das Geschlecht ist ungültig', 'invalid_gender', 400 );
    }

    $userInputData->firstName = $request->input('firstName');
    $userInputData->lastName = $request->input('lastName');
    $userInputData->street = $request->input('street');
    $userInputData->city = $request->input('city');
    $userInputData->country = $request->input('country');
    $userInputData->title = $request->input('title');

    $guessedSalutation = _guessSalutationFromGW($userInputData->firstName, $userInputData->lastName, $userInputData->gender, '', $userInputData->country);
    $userInputData->addressterm = $guessedSalutation->addressterm;
    $userInputData->addressletter = $guessedSalutation->addressletter;

    if($request->has('phone') && !empty($request->input('phone')) && !is_null($request->input('phone'))) {
        if(!is_numeric($request->input('phone'))) {
            return createErrorObject('Die Telefonnummer ist ungültig', 'invalid_phone', 400 );
        }
        $userInputData->phone = $request->input('phone');
    } else {
        $userInputData->phone = '';
    }

    $fields = new stdClass();
    $fields->CHRISTIANNAME = $userInputData->firstName;
    $fields->NAME = $userInputData->lastName;
    $fields->STREET3 = $userInputData->street;
    $fields->ZIP3 = $userInputData->zip;
    $fields->TOWN3 = $userInputData->city;
    $fields->COUNTRY3 = $userInputData->country;
    $fields->GWGENDER = $userInputData->gender;
    $fields->BIRTHDAY = $userInputData->birthdate;
    $fields->PHONEFIELDSTR7 = $userInputData->phone;
    $fields->ADDRESSLETTER = $userInputData->addressletter;
    $fields->ADDRESSTERM = $userInputData->addressterm;
    $fields->TITLE = $userInputData->title;

    if(property_exists($userInputData, 'salutation') && $userInputData->salutation != "") {
        $fields->ADDRESSTERM = $userInputData->salutation;
    }

    if(!updateGwAddressData($request->input('contact_person_gguid'), $fields)) {
        return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500 );
    }

    $personal_data = getGwPersonalDataByGGUID($request->input('contact_person_gguid'));

    if(isError($personal_data)) {
        return $personal_data;
    }

    if(!property_exists($personal_data, 'PHONEFIELDSTR7') || $personal_data->PHONEFIELDSTR7 == '' || strlen($personal_data->PHONEFIELDSTR7) <= 0) {
        $personal_data->PHONEFIELDSTR7 = '';
    }

    if(!property_exists($personal_data, 'ADDRESSTERM') || $personal_data->ADDRESSTERM == '' || strlen($personal_data->ADDRESSTERM) <= 0) {
        $personal_data->ADDRESSTERM = '';
    }

    return $personal_data;
}

function _getVMTransactionsForCardID($cardID, $type = '', $from = '', $to = '') {

    $vmTransactionsUrl = 'https://valuemaster.brain-behind.com/CU_WebAPI.asmx/CU_Show_TransactionsValueMaster';

    //workaround: generate random number to send as URL parameter to fix invalid response from the web application
    // firewall of valuemaster
    $seconds = time();
    $random = rand(1, 1000);
    $randomTime = sprintf('%d%d', $seconds, $random);
    $vmTransactionsUrl .= '?invalidateCache=' . $randomTime;

    $valueMasterResponse = Http::withHeaders([
        'provider' => 'trolleymaker',
        'password' => 'poiJJ#9q9',
        'Accept-Encoding' => 'gzip'
    ])->post($vmTransactionsUrl, [
        'CardID' => $cardID,
        'from' => $from,
        'to' => $to,
        'Type' => $type,
        'SystemID' => ''
    ]);

    if($valueMasterResponse && $valueMasterResponse != NULL) {
        if($valueMasterResponse['d']) {
            $transactions = json_decode($valueMasterResponse)->d;
            if($transactions && $transactions != NULL) {
                return $transactions;
            } else {
                Log::error('_getVMTransactionsForCardID: Die Transaktionen für Kartennummer ' . $cardID . ' konnten nicht abgerufen werden für: ' . $valueMasterResponse->body());
                return createErrorObject('Es ist ein Fehler aufgetreten.', 'no_transactions', 500);
            }
        } else {
            Log::error('_getVMTransactionsForCardID: Die Transaktionen für Kartennummer ' . $cardID . ' konnten nicht abgerufen werden für: ' . $valueMasterResponse->body());
            return createErrorObject('Es ist ein Fehler aufgetreten.', 'no_transactions', 500);
        }
    } else {
        Log::error('_getVMTransactionsForCardID: Die Transaktionen für Kartennummer ' . $cardID . ' konnten nicht abgerufen werden für: ' . $valueMasterResponse->body());
        return createErrorObject('Es ist ein Fehler aufgetreten.', 'no_transactions', 500);
    }

    return createErrorObject('Es ist ein Fehler aufgetreten.', 'no_transactions', 500);
}


function _checkIfBookingIsAllowedForCard($cardID, $partnerRegionName, $partnerCardName, $shouldAlsoCheckMax250AddVoucher = false, $amountCent = NULL) {

    $returnObject = new stdClass();
    $returnObject->isBookingAllowed = false;
    $returnObject->isCardRegistered = false;
    $returnObject->isTestcard = false;

    if(!isValidCardIDSyntax($cardID)) {
        Log::error('no valid cardID syntax: ' . print_r($cardID, true));
        return $returnObject;
    }

    $cardData = getCardForCardID($cardID);

    if(isError($cardData)) {
        return $cardData;
    }

    if(!property_exists($cardData, 'GGUID')) {
        $returnObject->isBookingAllowed = false;
        return $returnObject;
    }

    if(!property_exists($cardData, 'KVWKARTEAKTIVVM') || $cardData->KVWKARTEAKTIVVM !== true) {
        Log::error('Card is not active: ' . print_r($cardID, true));
        $returnObject->isBookingAllowed = false;
        return $returnObject;
    }

    if(!property_exists($cardData, 'GWSSTATUS') || strtolower($cardData->GWSSTATUS) !== 'aktiviert') {
        Log::error('Card is not active: ' . print_r($cardID, true));
        $returnObject->isBookingAllowed = false;
        return $returnObject;
    }

    if(property_exists($cardData, 'KVWKARTEREGISTRIERT') && $cardData->KVWKARTEREGISTRIERT === true) {
        $returnObject->isCardRegistered = true;
    }

    if(!property_exists($cardData, 'KVWORTDERANMELDUNG') || $cardData->KVWORTDERANMELDUNG == '' || !property_exists($cardData, 'KVWREGION') || $cardData->KVWREGION == '') {
        $returnObject->isBookingAllowed = false;
        $returnObject->errorMessage = 'Es konnte nicht geprüft werden, ob die Karte gültig ist.';
        $returnObject->errorStatusCode = 'unkown_error';
        $returnObject->httpStatusCode = 500;
        return $returnObject;
    }

    if(strtolower($cardData->KVWREGION) != 'testregion' && (strtolower($cardData->KVWORTDERANMELDUNG) != strtolower($partnerCardName) || strtolower($cardData->KVWREGION) != strtolower($partnerRegionName))) {
        Log::error('wrong region. CardID involved: ' . print_r($cardID, true));
        $returnObject->isBookingAllowed = false;
        return $returnObject;
    }


    $nowDate = new DateTime('now');
    $nowString = $nowDate->format('Y-m-d');
    $firstDayOfMonth = date("01.m.Y", strtotime($nowString));
    $lastDayOfMonth = date("t.m.Y", strtotime($nowString));
    $vmTransactions = _getVMTransactionsForCardID($cardID, 'Gutschein aktiv', $firstDayOfMonth, $lastDayOfMonth);

    $amountOfTheMonth = 0;
    foreach($vmTransactions as $transaction) {
        $tempAmount = str_replace(',', '.', $transaction->amount);
        $amountOfTheMonth += floatval($tempAmount);
    }

    $remainingAmountOfThisMonth = 250 - $amountOfTheMonth;
    $remainingAmountOfThisMonthFormatted = number_format(250 - $amountOfTheMonth, 2, ',', '.');
    $remainingAmountCentOfTheMonth = intval($remainingAmountOfThisMonth * 100);
    $currentAmountToBook = number_format(intval($amountCent) / 100, 2);

    if($amountOfTheMonth > 250) {
        $returnObject->remainingAmountCentToAddVoucherThisMonth = 0;
    } else if(($amountOfTheMonth + $currentAmountToBook) > 250) {
        $returnObject->remainingAmountCentToAddVoucherThisMonth = $remainingAmountCentOfTheMonth;
    } else {
        if($amountCent != null) {
            $returnObject->remainingAmountCentToAddVoucherThisMonth = intval($remainingAmountCentOfTheMonth - intval($amountCent));
        } else {
            $returnObject->remainingAmountCentToAddVoucherThisMonth = intval($remainingAmountCentOfTheMonth);
        }
    }
    $returnObject->remainingAmountToAddVoucherThisMonthFormattedDE = number_format($returnObject->remainingAmountCentToAddVoucherThisMonth / 100, 2, ',', '.');
    $returnObject->remainingAmountToAddVoucherThisMonthFormattedEN = number_format($returnObject->remainingAmountCentToAddVoucherThisMonth / 100, 2, '.', ' ');

    if($shouldAlsoCheckMax250AddVoucher) {
        if($amountOfTheMonth > 250) {
            $returnObject->isBookingAllowed = false;
            $returnObject->errorMessage = 'Es dürfen nur maximal 250€ pro Kalendermonat aufgebucht werden! Auf diese Karte darf diesen Monat kein Guthaben mehr aufgebucht werden.';
            $returnObject->errorStatusCode = 'max_monthly_amount_reached';
            $returnObject->httpStatusCode = 400;
        } else {
            if(($amountOfTheMonth + $currentAmountToBook) > 250) {
                $returnObject->isBookingAllowed = false;
                $returnObject->errorMessage = 'Es dürfen nur maximal 250€ pro Kalendermonat aufgebucht werden! Auf diese Karte dürfen diesen Monat noch ' . $remainingAmountOfThisMonthFormatted . '€ aufgebucht werden.';
                $returnObject->errorStatusCode = 'max_monthly_amount_reached';
                $returnObject->httpStatusCode = 400;
            } else {
                $returnObject->isBookingAllowed = true;
            }
        }
    } else {
        $returnObject->isBookingAllowed = true;
    }

    if(strtolower($cardData->KVWREGION) == 'testregion' || (property_exists($cardData, 'KVWISTTESTKARTE') && $cardData->KVWISTTESTKARTE == true)) {
        unset($returnObject->errorMessage);
        unset($returnObject->errorStatusCode);
        unset($returnObject->httpStatusCode);
        $returnObject->isBookingAllowed = true;
        $returnObject->isTestcard = true;
    }

    return $returnObject;
}

function api_getBalanceAmount($cardID) {
    $valueMasterResponse = Http::withoutVerifying()->withHeaders([
        'provider' => 'trolleymaker',
        'password' => 'poiJJ#9q9'
    ])->post('https://valuemaster.brain-behind.com/CU_WebAPI.asmx/CU_Check_Balance_Array', [
        'CardID' =>  $cardID,
        'TerminalID' => '',
    ]);

    $data = json_decode($valueMasterResponse)->d;

    if($data && $data != NULL) {
        if($data->errorMessage && $data->errorMessage != '') {
            return createErrorObject($data->errorMessage, 'unknown_error', 500);
        } else {
            $balance = 0;
            $balanceCent = 0;
            foreach ($data->CU_Guthaben_Array as $balance_object) {
                $balance = $balance + ($balance_object->value / 100);
                $balanceCent = $balanceCent + $balance_object->value;
            }

            return ['balanceFormattedDE' => number_format($balance, 2, ',', '.'), 'balanceFormattedEN' => number_format($balance, 2), 'balanceCent' => $balanceCent];
        }
    } else {
        return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
    }
}

function api_getIsExternalCard($cardID)
{
    $query = "SELECT * FROM EXTERNALCARDMAPPING WHERE INTERNALCARDNUMBER = '" . $cardID . "'";

    $gwResponse = Http::withHeaders([
                                        'Content-Type'      => 'application/json; charset=utf-8',
                                        'Accept'            => 'application/json',
                                        'Authorization'     => 'Basic ' . env("GW_AUTHORIZATION"),
                                        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA==',
                                    ])->post(env('GW_API_BASE') . '/query', [
        "query" => $query,
    ]);

    if ($gwResponse->failed()) {
        if ($gwResponse->status() == 503) {
            return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error("Fehler beim Abrufen von api_getIsExternalCard: " . $query . "\n" . print_r($gwResponse->body(), TRUE));

            return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
        }
    }

    if (count(json_decode($gwResponse)) <= 0 || !property_exists(json_decode($gwResponse)[0], 'rows') || count
        (json_decode($gwResponse)[0]->rows) == 0) {
        return false;
    }

    return true;
}

function api_getRegionId($regionName)
{
    $query = "SELECT id FROM CLIENTID WHERE RegionName = '" . $regionName . "'";

    $gwResponse = Http::withHeaders([
                                        'Content-Type'      => 'application/json; charset=utf-8',
                                        'Accept'            => 'application/json',
                                        'Authorization'     => 'Basic ' . env("GW_AUTHORIZATION"),
                                        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA==',
                                    ])->post(env('GW_API_BASE') . '/query', [
        "query" => $query,
    ]);

    if ($gwResponse->failed()) {
        if ($gwResponse->status() == 503) {
            return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error("Fehler beim Abrufen von api_getFullValueOnly: " . $query . "\n" . print_r($gwResponse->body(), TRUE));

            return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
        }
    }

    if (count(json_decode($gwResponse)) <= 0 || !property_exists(json_decode($gwResponse)[0], 'rows') || count(json_decode($gwResponse)[0]->rows) != 1) {
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
    }

    return json_decode($gwResponse->body())[0]->rows[0]->ID;
}

function api_getFullValueOnly($regionID)
{
    $query = "SELECT FullValueOnly FROM EXTERNALCARDSETTINGS WHERE ClientId = '" . $regionID . "'";

    $gwResponse = Http::withHeaders([
                                        'Content-Type'      => 'application/json; charset=utf-8',
                                        'Accept'            => 'application/json',
                                        'Authorization'     => 'Basic ' . env("GW_AUTHORIZATION"),
                                        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA==',
                                    ])->post(env('GW_API_BASE') . '/query', [
        "query" => $query,
    ]);

    if ($gwResponse->failed()) {
        if ($gwResponse->status() == 503) {
            return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error("Fehler beim Abrufen von api_getFullValueOnly: " . $query . "\n" . print_r($gwResponse->body(), TRUE));

            return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
        }
    }

    if (count(json_decode($gwResponse)) <= 0 || !property_exists(json_decode($gwResponse)[0], 'rows') || count(json_decode($gwResponse)[0]->rows) != 1) {
        return false;
    }

    return json_decode($gwResponse->body())[0]->rows[0]->FULLVALUEONLY;
}

function getCardNameImageUrl($card_name) {
    $slugify = new Slugify();
    $card_name_slugifyed = $slugify->slugify($card_name);
    return 'https://backend.mycity.cards/api/v1/cards-images/' . $card_name_slugifyed . '.png';
}

/**
 * return response the fallback image. if api key exists than the card image is return otherwise a placeholder image
 *
 * @param  string $card_name the card name of the region
 * @return file
 */
function returnFallbackImage($card_image_or_partner_name_image = 'card', $card_name = NULL, $partner_gguid = NULL) {
    if($card_image_or_partner_name_image == NULL || ($card_image_or_partner_name_image == 'card' && $card_name ==
            NULL)) {
        return response()->file(storage_path('app/cards-images/placeholder.png'));
    }
    if($card_image_or_partner_name_image == 'partner' || $card_image_or_partner_name_image == 'partner_name') {
        $company_data = getGwPersonalDataByGGUID($partner_gguid);
        if(isError($company_data) || !property_exists($company_data, 'GGUID') || !property_exists($company_data, 'COMPNAME')) {
            return response()->file(storage_path('app/cards-images/placeholder.png'));
        }

        $text = $company_data->COMPNAME;
        if(property_exists($company_data, 'COMPNAME2') && !empty($company_data->COMPNAME2)) {
            $text = $company_data->COMPNAME2;
        }


        $image = new Imagick();
        $draw = new ImagickDraw();
        $image->newImage(800, 200, 'none');

        // Load Font
        $starting_font_size = 60;
        $font_size = $starting_font_size;
        $draw->setFont(storage_path('app/roboto-regular.ttf'));
        $draw->setFontSize($font_size);

        $total_height = 0;
        $max_width = 800;
        $max_height = 200;
        $line_height_ratio = 0.85;
        $y_pos = 0;


        // Run until we find a font size that doesn't exceed $max_height in pixels
        while ( 0 == $total_height || $total_height > $max_height ) {
            if ( $total_height > 0 ) $font_size--; // we're still over height, decrement font size and try again

            $draw->setFontSize($font_size);

            // Calculate number of lines / line height
            $words = preg_split('%\s%', $text, -1, PREG_SPLIT_NO_EMPTY);
            $lines = array();
            $i = 0;
            $line_height = 1;

            while ( count($words) > 0 ) {
                $metrics = $image->queryFontMetrics( $draw, implode(' ', array_slice($words, 0, ++$i) ) );
                $line_height = max( $metrics['textHeight'], $line_height );

                if ( $metrics['textWidth'] > $max_width || count($words) < $i ) {
                    $lines[] = implode( ' ', array_slice($words, 0, --$i) );
                    $words = array_slice( $words, $i );
                    $i = 0;
                }
            }

            $total_height = count($lines) * $line_height * $line_height_ratio;

            if ( $total_height === 0 ) return false; // don't run endlessly if something goes wrong
        }

        // Writes text to image
        for( $i = 0; $i < count($lines); $i++ ) {
            $metrics = $image->queryFontMetrics($draw, $lines[$i]);
            $image->annotateImage( $draw, ($max_width - $metrics['textWidth']) / 2, $y_pos + (($i + 1) * $line_height * $line_height_ratio), 0, $lines[$i] );
        }

        $image->setBackgroundColor('none');
        $image->setImageFormat("png");

        return response($image->getImageBlob());
    }
    if($card_image_or_partner_name_image == 'card' && $card_name != NULL) {
        $slugify = new Slugify();
        $card_name_slugifyed = $slugify->slugify($card_name);
        return response()->file(storage_path('app/cards-images/' . $card_name_slugifyed . '.png'));
    }
    return response()->file(storage_path('app/cards-images/placeholder.png'));
}


function _getVMNowDate() {
    $dateNow = new DateTime('now');
    $dateNow->setTimezone(new DateTimeZone('Europe/Berlin'));
    $now = $dateNow->format('Y-m-d\TH:i:s.vP');
    return $now;
}

function _getGWNowDate() {
    $dateNow = new DateTime('now', new DateTimeZone('+0000'));
    $now = $dateNow->format('Y-m-d\TH:i:s');
    return $now;
}

function _generateJWT($session_token)
{
    $secret   = env('JWT_SECRET');
    $jwt_algo = env('JWT_ALGO');
    $payload  = [
        'iat'           => now()->unix(),
        'exp'           => now()->addMinutes(60)->unix(),
        'session_token' => $session_token,
    ];

    return JWT::encode($payload, env('JWT_SECRET'), env('JWT_ALGO'));
}

function _isValidAmountCent($amountCent) {
    if($amountCent == NULL || $amountCent == '' || !is_int($amountCent) || $amountCent === 0 || $amountCent === 0.0 || $amountCent === 0.00) {
        return false;
    }
    return true;
}

function _isPartner($request) {
    return $request->input('user_role') === UserRoles::PARTNER->value;
}

function _isInterest($request) {
    return $request->input('user_role') === UserRoles::INTERESTED->value;
}

function _isEmployer($request) {
    return $request->input('user_role') === UserRoles::EMPLOYER->value;
}

function _isCustomer($request) {
    return $request->input('user_role') === UserRoles::CUSTOMER->value;
}

function _isPartnerAdmin($request) {
    return $request->input('partner_user_role') === PartnerUserRoles::ADMIN->value;
}

function _isPartnerAdminOrUser($request) {
    return $request->input('partner_user_role') === PartnerUserRoles::ADMIN->value || $request->input('partner_user_role') === PartnerUserRoles::USER->value;
}


function isContainsCardIDInCustomerSession($request, $cardID) {
    return contains(strval($cardID), $request->input('cardIDs'));
}

function sendErrorNotificationMail($message) {
    $errorData = new stdClass();
    $errorData->message = $message;
    if(App::environment(['production', 'live'])) {
        Mail::send(new ErrorNotificationMail($errorData));
    }
}

function sendPushNotification($gwPushNotificationObject) {

    if(!property_exists($gwPushNotificationObject, 'PNTITEL') || $gwPushNotificationObject->PNTITEL == NULL || empty($gwPushNotificationObject->PNTITEL)) {
        return createErrorObject('Es wurde kein Titel angegeben!', 'no_pntitel', 400);
    }

    if(!property_exists($gwPushNotificationObject, 'PNNACHRICHT') || $gwPushNotificationObject->PNNACHRICHT == NULL || empty($gwPushNotificationObject->PNNACHRICHT)) {
        return createErrorObject('Es wurde keine Nachricht angegeben!', 'no_pnnachricht', 400);
    }

    $firebase_project_id = 1;
    $regions = explode(',', env('REGIONS_API_KEYS'));
    foreach ($regions as $region) {
        $tempRegion = explode(':', $region);
        if(strtolower($tempRegion[1]) == strtolower($gwPushNotificationObject->PNAUSWAHLREGION)) {
            $firebase_project = $tempRegion[4];
            $firebase_project_id = explode('-', $firebase_project)[1];
        }
    }

    $factory = (new Factory)->withServiceAccount(env('PATH_TO_FIREBASE_CREDENTIALS' . $firebase_project_id));
    $messaging = $factory->createMessaging();

    $deviceTokens = getFirebaseClientsRecipientsForPushNotification($gwPushNotificationObject);

    if(isError($deviceTokens)) {
        Log::error('error device tokens');
        return $deviceTokens;
    }

    if(count($deviceTokens) == 0) {
        Log::error('Für die folgenden Angaben wurden keine für Push-Notifications registrierten Geräte gefunden/angemeldet: ' . print_r($gwPushNotificationObject, true));
        return createErrorObject('Mit diesen Angaben wurden keine, für Push-Notifications registrierten, Geräte gefunden', 'no_registered_devices', 400);
    }

    if(property_exists($gwPushNotificationObject, 'PNPRIORITAET') && strtolower($gwPushNotificationObject->PNPRIORITAET) == 'hoch') {
        //high priority message
        $message = CloudMessage::new()
            ->withNotification(Notification::create($gwPushNotificationObject->PNTITEL, $gwPushNotificationObject->PNNACHRICHT))
            ->withDefaultSounds()
            ->withHighestPossiblePriority();
    } else {
        //normal priority message

        $message = CloudMessage::new()
            ->withNotification(Notification::create($gwPushNotificationObject->PNTITEL, $gwPushNotificationObject->PNNACHRICHT))
            ->withDefaultSounds();
    }

    $firebaseIds = array_column($deviceTokens, 'FBFIREBASEID');
    $report = $messaging->sendMulticast($message, $firebaseIds);
    Log::error('push message: ' . print_r($message, true));
    Log::error('Successful sends: ' . $report->successes()->count());
    Log::error('Failed sends: ' . $report->failures()->count());

    if ($report->hasFailures()) {
        foreach ($report->failures()->getItems() as $failure) {
            Log::error($failure->error()->getMessage());
        }
    }

    if($report->successes()->count() == 0) {
        return createErrorObject('Die Push Benachrichtigung wurde zwar gespeichert, aber es gab anscheinend einen Fehler beim Versenden der Push Benachrichtigung.', 'error_sending_push_notification', 500);
    }

    if($report->successes()->count() > 0) {
        $pushNotificationFieldsToUpdate = new stdClass();
        $pushNotificationFieldsToUpdate->GWSSTATUS = 'versendet';
        $pushNotificationFieldsToUpdate->PNVERSANDZEITPUNKT = _getGWNowDate();
        //$pushNotificationFieldsToUpdate->PNANZAHLGERAETE = $report->successes()->count();
        $updatedPushNotification = updateGwPushNotificationData($gwPushNotificationObject->GGUID, $pushNotificationFieldsToUpdate);
        if(isError($updatedPushNotification)) {
            Log::error('Die Push Nachricht mit GGUID: ' . $gwPushNotificationObject->GGUID . ' konnte nicht aktualisiert werden, dass sie abgeschickt wurde.');
            sendErrorNotificationMail('Die Push Nachricht mit GGUID: ' . $gwPushNotificationObject->GGUID . ' konnte nicht aktualisiert werden, dass sie abgeschickt wurde.');
        }
    }

    $successfulTargets = $report->validTokens();
    $unknownTargets = $report->unknownTokens();
    $invalidTargets = $report->invalidTokens();
    Log::debug('successfull targets: ' . print_r($successfulTargets, true));
    Log::debug('unknown targets: ' . print_r($unknownTargets, true));
    Log::debug('invalid targets: ' . print_r($invalidTargets, true));

    if(count($invalidTargets) > 0) {
        deleteFirebaseClientsFoDeviceIDs($invalidTargets);
    }

    return true;
}


function deleteFirebaseClientsFoDeviceIDs($deviceIdsToDelete) {
    if(count($deviceIdsToDelete) > 0) {
        $invalidFirebaseClients = _getGwFirebaseClientsForFirebaseIDs('*', $deviceIdsToDelete);
        if(isError($invalidFirebaseClients) || $invalidFirebaseClients == NULL || count($invalidFirebaseClients) == 0) {
            Log::error('Die ungültigen Firebase Clients ' . print_r($deviceIdsToDelete, true) . ' konnte nicht abgerufen werden, um sie nach dem Versenden der Push Nachricht zu löschen/deaktivieren.');
            sendErrorNotificationMail('Die ungültigen Firebase Clients ' . print_r($deviceIdsToDelete, true) . ' konnte nicht abgerufen werden, um sie nach dem Versenden der Push Nachricht zu löschen/deaktivieren.');
            return $invalidFirebaseClients;
        }

        foreach ($invalidFirebaseClients as $firebaseClient) {
            $deletionResponse = _deleteGwFirebaseClient($firebaseClient->GGUID);
            if(isError($deletionResponse)) {
                Log::error('Der ungültigen Firebase Client ' . $firebaseClient->GGUID . ' konnte nicht gelöscht werden, um sie nach dem Versenden der Push Nachricht zu löschen/deaktivieren.');
                sendErrorNotificationMail('Der ungültigen Firebase Client ' . $firebaseClient->GGUID . ' konnte nicht gelöscht werden, um sie nach dem Versenden der Push Nachricht zu löschen/deaktivieren.');
                return $deletionResponse;
            }
        }
    }

    return new stdClass();
}

function getFirebaseClientsRecipientsForPushNotification($pushNotification) {

    Log::error(print_r($pushNotification,true));
    if($pushNotification == NULL) {
        Log::error('Error in getFirebaseClientsRecipientsForPushNotification, es wurden keine PushNotification angegeben');
        return createErrorObject('Es wurden keine PushNotification angegeben.', 'no_firebaseIDs', 400);
    }

    if(!property_exists($pushNotification, 'PNAUSWAHLREGION') || $pushNotification->PNAUSWAHLREGION == NULL) {
        Log::error('Error in getFirebaseClientsRecipientsForPushNotification, es wurden keine Region in der Push Benachrichtigung angegeben.');
        return createErrorObject('Es wurden keine Region in der Push Benachrichtigung angegeben.', 'no_region', 400);
    }

    $query = 'SELECT * FROM FIREBASENUMMERN AS f';
    $addedWhere = false;
    if(strtolower($pushNotification->PNAUSWAHLREGION) !== 'alle') {
        $addedWhere = true;
        $query .= " WHERE f.FBREGION = '" . $pushNotification->PNAUSWAHLREGION . "'";
    }
    if(strtolower($pushNotification->PNAUSWAHLVERSANDCLUSTER) !== 'alle') {
        if(strtolower($pushNotification->PNAUSWAHLVERSANDCLUSTER) === 'kunden') {
            if($addedWhere) {
                $query .= " AND f.GWSTYPE = 'Kunde'";
            } else {
                $query .= " WHERE f.GWSTYPE = 'Kunde'";
            }
        } else if(strtolower($pushNotification->PNAUSWAHLVERSANDCLUSTER) === 'partner') {
            if($addedWhere) {
                $query .= " AND f.GWSTYPE = 'Partner'";
            } else {
                $query .= " WHERE f.GWSTYPE = 'Partner'";
            }
        }
    }

    if(strtolower($pushNotification->GWSTYPE) == 'testnachricht') {
        if($addedWhere) {
            $query .= " AND f.IsLinkedToWhere(address AS a:WHERE a.TMISTTESTDATENSATZ = true; LinkAttribute='TMFIREBASEKUNDE')";
        } else {
            $query .= " WHERE f.IsLinkedToWhere(address AS a:WHERE a.TMISTTESTDATENSATZ = true; LinkAttribute='TMFIREBASEKUNDE')";
        }
    } else {
        if(strtolower($pushNotification->PNAUSWAHLKARTEN) == 'registrierte') {
            if($addedWhere) {
                $query .= " AND f.IsLinkedToWhere(address;LinkAttribute='TMFIREBASEKUNDE')";
            } else {
                $query .= " WHERE f.IsLinkedToWhere(address;LinkAttribute='TMFIREBASEKUNDE')";
            }
        } else if(strtolower($pushNotification->PNAUSWAHLKARTEN) == 'nicht registrierte') {
            if($addedWhere) {
                $query .= " AND NOT f.IsLinkedToWhere(address;LinkAttribute='TMFIREBASEKUNDE')";
            } else {
                $query .= " WHERE NOT f.IsLinkedToWhere(address;LinkAttribute='TMFIREBASEKUNDE')";
            }
        }
    }

/*
    $query = str_replace('"', '', $query);
    $query = str_replace("'", '"', $query);
*/

    Log::debug('function getFirebaseClientsRecipientsForPushNotification query: ' . $query);

    $gwResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/query', [
        "query" => $query
    ]);

    if($gwResponse->failed()) {
        if($gwResponse->status() == 503) {
            return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error('getFirebaseClientsRecipientsForPushNotification: ' . $query . '\n\n' . $gwResponse->body());
            return createErrorObject('Es ist ein Fehler aufgetreten.', 'unknown_error', 500);
        }
    }

    if(count(json_decode($gwResponse)) <= 0 || !property_exists(json_decode($gwResponse)[0], 'rows') || count(json_decode($gwResponse)[0]->rows) <= 0) {
        Log::error('getFirebaseClientsRecipientsForPushNotifications: no addresses found');
        return [];
    }

    $gwRecipientFirebaseClients = json_decode($gwResponse)[0]->rows;

    Log::debug('alle firebase IDs die als Empfänger in Frage kommen: ' . print_r($gwRecipientFirebaseClients, true));

    return $gwRecipientFirebaseClients;
}

function getGWFirebasenummerByFirebaseId($firebaseId) {
    $validator = Validator::make([
                                     'firebaseId'     => $firebaseId,
                                 ], [
                                     'firebaseId'     => 'required|string',
                                 ]);

    if ($validator->fails()) {
        Log::error('checkIfFirebasenummerExists: validation failed for firebaseId: ' . print_r($firebaseId, true));
        return false;
    }


    $query = "SELECT * FROM FIREBASENUMMERN WHERE FBFIREBASEID='" . $firebaseId . "'";

    $gwResponse = Http::withHeaders([
                                        'Content-Type' => 'application/json; charset=utf-8',
                                        'Accept' => 'application/json',
                                        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
                                        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
                                    ])->post(env('GW_API_BASE') . '/query', [
        "query" => $query
    ]);

    if($gwResponse->failed()) {
        if($gwResponse->status() == 503) {
            return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error("Fehler beim Abrufen von checkIfFirebasenummerExists: " . $query . "\n" . print_r($gwResponse->body(), true));
            return false;
        }
    }

    if(count(json_decode($gwResponse)) <= 0 || !property_exists(json_decode($gwResponse)[0], 'rows') || count(json_decode($gwResponse)[0]->rows) <= 0) {
        return false;
    }

    return json_decode($gwResponse)[0]->rows[0];
}

Route::get('/push-notifications', function (Request $request) {

    $values = _getSuggestedValuesForPushNotifications(['PNAUSWAHLREGION', 'PNAUSWAHLVERSANDCLUSTER', 'PNAUSWAHLKATEGORIE', 'PNAUSWAHLKARTEN', 'PNPRIORITAET']);

    if(isError($values)){
        return returnErrorObject($values);
    }

    $types = _getSuggestedTypesForPushNotifications();
    $values['types'] = $types;

    $values['regions'] = $values['PNAUSWAHLREGION'];
    unset($values['PNAUSWAHLREGION']);

    $values['recipientCluster'] = $values['PNAUSWAHLVERSANDCLUSTER'];
    unset($values['PNAUSWAHLVERSANDCLUSTER']);

    $values['recipientPartnerCategories'] = $values['PNAUSWAHLKATEGORIE'];
    unset($values['PNAUSWAHLKATEGORIE']);

    $values['recipientCardsRegistered'] = $values['PNAUSWAHLKARTEN'];
    unset($values['PNAUSWAHLKARTEN']);

    $values['priorities'] = $values['PNPRIORITAET'];
    unset($values['PNPRIORITAET']);

    return response()->json($values, 200);
})->middleware(['AuthenticateWithSession', 'AuthenticateIsTrolleymaker']);


//create new push notification in GW
Route::post('/push-notifications', function (Request $request) {

    $pushNotificationFields = new stdClass();

    if(!$request->has('pushNotificationType') || $request->input('pushNotificationType') == NULL || empty($request->input('pushNotificationType'))) {
        return returnNewErrorObject('Es wurde kein Typ angegeben!', 'no_pushNotificationType', 400);
    }
    if(!$request->has('title') || $request->input('title') == NULL || empty($request->input('title'))) {
        return returnNewErrorObject('Es wurde kein Titel angegeben!', 'no_title', 400);
    }
    if(!$request->has('message') || $request->input('message') == NULL || empty($request->input('message'))) {
        return returnNewErrorObject('Es wurde keine Nachricht angegeben!', 'no_message', 400);
    }

    $pushNotificationFields->GWSTYPE = trim($request->input('pushNotificationType'));
    $pushNotificationFields->PNNACHRICHT = trim($request->input('message'));
    $pushNotificationFields->PNTITEL = trim($request->input('title'));

    if(!$request->has('recipientRegion') || $request->input('recipientRegion') == NULL || empty($request->input('recipientRegion'))) {
        return returnNewErrorObject('Es wurde keine Empfänger Region angegeben!', 'no_recipientRegion', 400);
    }
    if(!$request->has('recipientPartnerOrCustomer') || $request->input('recipientPartnerOrCustomer') == NULL || empty($request->input('recipientPartnerOrCustomer'))) {
        return returnNewErrorObject('Es wurde keine Empfänger Typ angegeben!', 'no_recipientPartnerOrCustomer', 400);
    }

    if($pushNotificationFields->GWSTYPE == 'Nachricht' || $pushNotificationFields->GWSTYPE == 'Testnachricht') {
        if(!$request->has('sendImmediately') || $request->input('sendImmediately') == NULL || empty($request->input('sendImmediately'))) {
            return returnNewErrorObject('Es wurde kein Versandzeitpunkt angegeben!', 'no_sendImmediately', 400);
        }
        if(!$request->has('priority') || $request->input('priority') == NULL || empty($request->input('priority'))) {
            return returnNewErrorObject('Es wurde keine Dringlichkeit / Priorität angegeben!', 'no_priority', 400);
        }
    } else if($pushNotificationFields->GWSTYPE == 'Georeferenzierung') {
        if(!$request->has('latitude') || $request->input('latitude') == NULL || empty($request->input('latitude'))) {
            return returnNewErrorObject('Es wurde kein Breitengrad angegeben!', 'no_latitude', 400);
        }
        if(!$request->has('longitude') || $request->input('longitude') == NULL || empty($request->input('longitude'))) {
            return returnNewErrorObject('Es wurde kein Längengrad angegeben!', 'no_longitude', 400);
        }
    }

    $suggestedValues = _getSuggestedValuesForPushNotifications(['PNAUSWAHLREGION', 'PNAUSWAHLVERSANDCLUSTER', 'PNAUSWAHLKATEGORIE', 'PNAUSWAHLKARTEN', 'PNPRIORITAET']);


    if(!in_array($request->input('recipientPartnerOrCustomer'), $suggestedValues['PNAUSWAHLVERSANDCLUSTER'])) {
        return returnNewErrorObject('Der angegebene Typ ist ungültig!', 'invalid_recipientPartnerOrCustomer', 400);
    }
    $pushNotificationFields->PNAUSWAHLVERSANDCLUSTER = $request->input('recipientPartnerOrCustomer');

    $recipientPartnerOrCustomerLowercased = strtolower($request->input('recipientPartnerOrCustomer'));
    if($recipientPartnerOrCustomerLowercased == 'alle' || $recipientPartnerOrCustomerLowercased == 'kunden') {
        if(!$request->has('recipientCardsRegistered') || $request->input('recipientCardsRegistered') == NULL || empty($request->input('recipientCardsRegistered'))) {
            return returnNewErrorObject('Wenn als Empfänger Typ alle oder Kunden ausgewählt ist, muss angegeben werden, ob alle oder nur registrierte / nicht registrierte Karten als Empfänger in Frage kommen!', 'no_recipientCardsRegistered', 400);
        }
        if(!in_array($request->input('recipientCardsRegistered'), $suggestedValues['PNAUSWAHLKARTEN'])) {
            return returnNewErrorObject('Ungültiger Wert für registrierte / nicht registrierte / alle!', 'invalid_recipientCardsRegistered', 400);
        }
        $pushNotificationFields->PNAUSWAHLKARTEN = $request->input('recipientCardsRegistered');

        if(!$request->has('recipientPartnerCategories') || !is_array($request->input('recipientPartnerCategories'))) {
            return returnNewErrorObject('Ungültiger Wert für Partner-Kategorien', 'invalid_recipientPartnerCategories', 400);
        }
        $pushNotificationFields->PNAUSWAHLKATEGORIE = implode(', ', $request->input('recipientPartnerCategories'));
    }

    if(!in_array($request->input('recipientRegion'), $suggestedValues['PNAUSWAHLREGION'])) {
        return returnNewErrorObject('Die angegebene Region ist ungültig!', 'invalid_recipientRegion', 400);
    }
    $pushNotificationFields->PNAUSWAHLREGION = $request->input('recipientRegion');


    if($pushNotificationFields->GWSTYPE == 'Nachricht' || $pushNotificationFields->GWSTYPE == 'Testnachricht') {
        if(!in_array($request->input('priority'), $suggestedValues['PNPRIORITAET'])) {
            return returnNewErrorObject('Die angegebene Dringlichkeit / Priorität ist ungültig!', 'invalid_priority', 400);
        }
        $pushNotificationFields->PNPRIORITAET = $request->input('priority');

        $sendImmediatelyLowercase = strtolower($request->input('sendImmediately'));
        if($sendImmediatelyLowercase != 'immediately' && $sendImmediatelyLowercase != 'later') {
            return returnNewErrorObject('Der angegebene Versandzeitpunkt ist ungültig!', 'invalid_sendImmediately', 400);
        }

        $pushNotificationFields->GWSSTATUS = 'wartend'; // yes, wartend for both options - it will changed to versendet after request to firebase successfully
        if($sendImmediatelyLowercase == 'immediately') {
            $pushNotificationFields->PNSOFORTIGERVERSAND = true;
            $pushNotificationFields->PNGEPLANTERVERSAND = false;
        } else if($sendImmediatelyLowercase == 'later') {
            $pushNotificationFields->PNSOFORTIGERVERSAND = false;
            $pushNotificationFields->PNGEPLANTERVERSAND = true;
            if(!$request->has('dateToSend') || $request->input('dateToSend') == NULL || empty($request->input('dateToSend'))) {
                return returnNewErrorObject('Wenn Versandzeitpunkt später ist, muss ein Versanddatum angegeben werden!', 'no_dateToSend', 400);
            }
            if(!$request->has('timeToSend') || $request->input('timeToSend') == NULL || empty($request->input('timeToSend'))) {
                return returnNewErrorObject('Wenn Versandzeitpunkt später ist, muss eine Versanduhrzeit angegeben werden!', 'no_timeToSend', 400);
            }

            $timestampToSend = $request->input('dateToSend') . ' ' . $request->input('timeToSend') . ':00';
            if(!validateDate($timestampToSend, 'Y-m-d H:i:s')) {
                return returnNewErrorObject('Der angegebene Versandzeitstempel ist ungültig!', 'invalid_datetimeToSend', 400);
            }
            $dateTimeToSend = new DateTime($timestampToSend, new DateTimeZone('Europe/Berlin'));
            $pushNotificationFields->PNGEPLANTERVERSANDZEITPUNKT = dateTimeToGWDateTime($dateTimeToSend);
        }
    } else if($pushNotificationFields->GWSTYPE == 'Georeferenzierung') {
        $lat = $request->input('latitude');
        if(str_contains($lat, '.')) {
            $lat = str_replace('.', ',', $lat);
        }
        $lng = $request->input('longitude');
        if(str_contains($lng, '.')) {
            $lng = str_replace('.', ',', $lng);
        }
        $pushNotificationFields->PNLATITUDE = $lat;
        $pushNotificationFields->PNLONGITUDE = $lng;
        $pushNotificationFields->GWSSTATUS = 'aktiviert';

        if(!empty($request->input('startDate'))) {
            $timestampStartDate = $request->input('startDate') . ' ' . $request->input('startTime') . ':00';
            if(!validateDate($timestampStartDate, 'Y-m-d H:i:s')) {
                return returnNewErrorObject('Der angegebene Startzeitpunkt ist ungültig!', 'invalid_startDate', 400);
            }
            $dateTimeStartTime = new DateTime($timestampStartDate, new DateTimeZone('Europe/Berlin'));
            $pushNotificationFields->PNGUELTIGAB = dateTimeToGWDateTime($dateTimeStartTime);
        }

        if(!empty($request->input('endDate'))) {
            $timestampEndDate = $request->input('endDate') . ' ' . $request->input('endTime') . ':00';
            if(!validateDate($timestampEndDate, 'Y-m-d H:i:s')) {
                return returnNewErrorObject('Der angegebene Endzeitpunkt ist ungültig!', 'invalid_endDate', 400);
            }
            $dateTimeEndTime = new DateTime($timestampEndDate, new DateTimeZone('Europe/Berlin'));
            $pushNotificationFields->PNGUELTIGBIS = dateTimeToGWDateTime($dateTimeEndTime);
        }
    }

    $pushNotificationGGUID = _createPushNotificationInGw($pushNotificationFields);
    if(isError($pushNotificationGGUID)) {
        return returnErrorObject($pushNotificationGGUID);
    }

    if($pushNotificationFields->GWSTYPE == 'Nachricht' || $pushNotificationFields->GWSTYPE == 'Testnachricht') {
        if(strtolower($request->input('sendImmediately')) == 'immediately') {
            $pushNotificationFields->GGUID = $pushNotificationGGUID;
            try {
                $sendPushNotificationResponse = sendPushNotification($pushNotificationFields);
                if(isError($sendPushNotificationResponse)) {
                    Log::error('Fehler beim Versenden der Push-Notification: ' . $sendPushNotificationResponse->errorMessage);
                    return returnErrorObject($sendPushNotificationResponse);
                }
            } catch(Throwable $exception) {
                Log::error('Fehler (catch) beim Versenden der Push-Notification: ' . $exception->getMessage());
                return returnNewErrorObject('Es ist ein Fehler aufgetreten. Bitte versuchen Sie es später erneut.', 'unknown_error', 500);
            }
        }
    }


    return response()->json(new stdClass(), 200);
})->middleware(['AuthenticateWithSession', 'AuthenticateIsTrolleymaker']);


Route::get('/reset-password-tokens', function (Request $request) {

    $users = DB::table('mycitycards_sessions')->select(['id', 'email', 'password_reset_token', 'password_reset_timestamp', 'card_name'])->whereNotNull('password_reset_token')->get();
    if($users == NULL || !$users) {
        return response()->json( [], 200 );
    }

    if(count($users) > 0) {
        foreach ($users as $index => $user) {
            if(property_exists($user, 'password_reset_timestamp') && $user->password_reset_timestamp != NULL) {
                $user->password_reset_timestamp = gWDateToGermanDateAndTime($user->password_reset_timestamp);
            }
            $user->reset_password_link = "https://mycity.cards/new-password?t=" . $user->password_reset_token;
        }
    }

    return response()->json( $users, 200 );

})->middleware(['AuthenticateWithSession', 'AuthenticateIsTrolleymaker']);


Route::get('/images/{documentGguid}/image.png', function (Request $request, string $documentGguid) {

    $gwGetLogo = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->get(env('GW_API_BASE') . '/type/document/' . $documentGguid . '/file');

    if($gwGetLogo->successful()) {
        return response($gwGetLogo->body())->header('Content-Type', 'image/png');
    }

    if($gwGetLogo->failed()) {
        return returnFallbackImage(NULL);
    }
})->withoutMiddleware([AuthenticateWithApiKey::class])->middleware(CheckIfApiKeyForRegion::class);


function _getPerksForAddress($addressGguid) {

    if($addressGguid == NULL || !$addressGguid || empty($addressGguid)) {
        Log::error('No addressgguid in _getPerksForAddress');
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
    }

    $checkGwLink = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->get(env('GW_API_BASE') . '/type/BSPRODUCT/full?linked-to=' . $addressGguid .'&linked-to-type=ADDRESS&linked-to-attributes=Prod2Kunde');

    if($checkGwLink->failed()) {
        Log::error("Fehler beim Abrufen der Verknüpfungen in _getPerksForAddress: " . $checkGwLink->body());
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500 );
    }

    $perkLinks = json_decode($checkGwLink);

    if(!$perkLinks || count($perkLinks) === 0) {
        return [];
    }

    $perks = [];
    foreach ($perkLinks as $perk) {

        $perk = $perk->fields;

        $getPerkGroupLink = Http::withHeaders([
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
            'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
        ])->get(env('GW_API_BASE') . '/type/BSPRODUCTGROUP/full?linked-to=' . $perk->GGUID .'&linked-to-type=BSPRODUCT&linked-to-attributes=BELONGSTOBPRBPG');

        if($getPerkGroupLink->failed()) {
            Log::error("Fehler beim Abrufen der Verknüpfungen in _getPerksForAddress: " . $getPerkGroupLink->body());
            return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500 );
        }

        $perkGroupLink = json_decode($getPerkGroupLink);

        if(!$perkGroupLink || count($perkGroupLink) === 0) {
            continue;
        } else {
            if(count($perkGroupLink) > 1) {
                Log::error("Fehler beim Abrufen von _getPerksForAddress (" . $addressGguid . "): Es wurden mehrere Verknüpfungen des Perks zur Perkgruppe gefunden: " . print_r($getPerkGroupLink->body(), true));
                return createErrorObject('Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie den Support.', 'unknown_error', 500);
            }
        }

        $perkGroup = $perkGroupLink[0]->fields;


        $getPerkDocuments = Http::withHeaders([
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
            'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
        ])->get(env('GW_API_BASE') . '/type/DOCUMENT/full?linked-to=' . $perk->GGUID .'&linked-to-type=BSPRODUCT&linked-to-attributes=AktionsDok2Prod');

        if($getPerkDocuments->failed()) {
            Log::error("Fehler beim Abrufen der Verknüpfungen in _getPerksForAddress: " . $getPerkDocuments->body());
            return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500 );
        }

        $perkDocumentsData = json_decode($getPerkDocuments);

        $perkDocuments = [];
        if(count($perkDocumentsData) > 0) {
            $perkDocuments = array_column(array_column($perkDocumentsData, 'fields'), 'EXTERNALLINK');
        }

        $tempPerk = new stdClass();

        $tempPerk->perkValue = property_exists($perk, 'NOTES') ? $perk->NOTES : '';
        $tempPerk->availableFrom = property_exists($perk, 'AVAILABLEFROM') ? $perk->AVAILABLEFROM : '';
        $tempPerk->availableUntil = property_exists($perk, 'AVAILABLEUNTIL') ? $perk->AVAILABLEUNTIL : '';
        $tempPerk->urlVendor = property_exists($perkGroup, 'TMLINKZURSTARTSEITEHERSTELLER') ? $perkGroup->TMLINKZURSTARTSEITEHERSTELLER : '';
        $tempPerk->urlVendorPlayStore = property_exists($perkGroup, 'TMLINKZURHERSTELLERAPPANDROID') ? $perkGroup->TMLINKZURHERSTELLERAPPANDROID : '';
        $tempPerk->urlVendorAppStore = property_exists($perkGroup, 'TMLINKZURHERSTELLERAPPAPPLE') ? $perkGroup->TMLINKZURHERSTELLERAPPAPPLE : '';
        $description = property_exists($perk, 'DESCRIPTION') ? $perk->DESCRIPTION : '';
        if(!empty($description)) {
            $description = str_replace("\r\n", "<br />", $description);
            $description = str_replace("\r", "<br />", $description);
            $description = str_replace("\n", "<br />", $description);
        }
        $tempPerk->description = $description;
        $tempPerk->documents = $perkDocuments;
        array_push($perks, $tempPerk);
    }

    return $perks;
}


function _getNextAvailablePerk() {

    $gwPerksResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/query', [
        "query" => "SELECT product.* FROM BSPRODUCT product LINK_JOIN(linkattribute='BELONGSTOBPRBPG') BSPRODUCTGROUP productgroup WHERE productgroup.BPGNUMBER = '7' AND product.TMSTATUSGUTSCHEIN = 'lagernd'"
    ]);

    if($gwPerksResponse->failed()) {
        if($gwPerksResponse->status() == 503) {
            return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error('getCardsAndAddressForEmployer: \n\n' . $gwPerksResponse->body());
            return createErrorObject('Es ist ein unbekannter Fehler aufgetreten.', 'unknown_error', 500);
        }
    }

    if(count(json_decode($gwPerksResponse)) == 0) {
        return NULL;
    }

    $gwPerksData = json_decode($gwPerksResponse)[0]->rows;

    return $gwPerksData[0];
}

function isPrefilledInterestPartnerOrCustomer($partnerCompanyGguid, $hashedGGUID) {
    $company_data = getGwPersonalDataByGGUID($partnerCompanyGguid);
    $response = new stdClass();
    $response->isAllowedToPrefill = false;
    if(isError($company_data) || !property_exists($company_data, 'GGUID')) {
        Log::error('Die Daten der vorausgefüllten Firma oder Kunden für die Interessentenregistrierung konnte nicht gefunden werden. hashed ID: ' . $hashedGGUID . ', partnerCompanyGguid: ' . $partnerCompanyGguid);
        sendErrorNotificationMail('Die Daten der vorausgefüllten Firma oder Kunden für die Interessentenregistrierung konnte nicht gefunden werden. hashed ID: ' . $hashedGGUID . ', partnerCompanyGguid: ' . $partnerCompanyGguid);
        return $response;
    }
    if(property_exists($company_data, 'GWSTYPE') && (strtolower($company_data->GWSTYPE) == 'potentielle partnerschaft' || strtolower($company_data->GWSTYPE) == 'kunde')
        && property_exists($company_data, 'TMPERSWEBSITELINKINVALID') && is_bool($company_data->TMPERSWEBSITELINKINVALID) && $company_data->TMPERSWEBSITELINKINVALID === false
        && property_exists($company_data, 'TMINTERESSENTREGUEBERSPRINGEN') && is_bool($company_data->TMINTERESSENTREGUEBERSPRINGEN) && $company_data->TMINTERESSENTREGUEBERSPRINGEN === true) {
        $response->isAllowedToPrefill = true;
        $response->company_data = $company_data;
    }

    return $response;
}


function getAllActiveGamesAndActionsForRegion($region, $onlyShowWithActivePlaytime = true) {

    $validator = Validator::make([
        'region'     => $region,
    ], [
        'region'     => 'required|regex:/[\pL\-+\s*_]*$/',
    ]);

    if ($validator->fails()) {
        Log::error('Validation Error in getAllActiveGamesAndActionsForRegion, Region: ' . print_r($region,true));
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'no_region',
            400);
    }

    $now = _getGWNowDate();

    $query = "SELECT * FROM SPIELEUNDAKTIONEN WHERE GWSSTATUS = 'aktiv' AND TMGUELTIGVON < '" . $now . "' AND TMGUELTIGBIS > '" . $now . "' AND TMREGION = '" . $region . "'";

    $gwResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/query', [
        "query" => $query
    ]);

    if($gwResponse->failed()) {
        if($gwResponse->status() == 503) {
            return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error("Fehler beim Abrufen von getAllActiveGamesAndActionsForRegion: " . $query . "\n" . print_r($gwResponse->body(), true));
            return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
        }
    }

    if(count(json_decode($gwResponse)) <= 0 || !property_exists(json_decode($gwResponse)[0], 'rows') || count(json_decode($gwResponse)[0]->rows) <= 0) {
        return [];
    }

    $gamesAndActions = json_decode($gwResponse)[0]->rows;
    $activeGamesAndActions = [];
    if($onlyShowWithActivePlaytime) {
        foreach ($gamesAndActions as $gamesAndAction) {
            if(property_exists($gamesAndAction, 'TMSPIELZEITBEACHTEN') && $gamesAndAction->TMSPIELZEITBEACHTEN == true) {
                if(!property_exists($gamesAndAction, 'TMTMBEGINNSPIELZEIT') || !property_exists($gamesAndAction, 'TMENDESPIELZEIT')) {
                    Log::error('Ein Spiel und Aktionen hat das Feld TMSPIELZEITBEACHTEN auf true gesetzt, aber die Spielzeit Felder nicht ausgefüllt');
                    continue;
                }
                $nowDate = new DateTime($now);
                $startDate = new DateTime($gamesAndAction->TMTMBEGINNSPIELZEIT);
                $endDate = new DateTime($gamesAndAction->TMENDESPIELZEIT);
                if($nowDate < $startDate || $nowDate > $endDate) {
                    continue;
                }
            }
            $activeGamesAndActions[] = $gamesAndAction;
        }
    } else {
        $activeGamesAndActions = $gamesAndActions;
    }

    return $activeGamesAndActions;
}


function getItemsForGamesAndActionsGguid($gamesAndActionsGguid, $customerGguid = NULL) {

    $validator = Validator::make([
        'gamesAndActionsGguid'     => $gamesAndActionsGguid,
    ], [
        'gamesAndActionsGguid'     => 'required',
    ]);

    if ($validator->fails()) {
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'no_gamesandactions',
            400);
    }

    $query = "SELECT * FROM SUAITEMS AS i WHERE i.IsLinkedToWhere(SPIELEUNDAKTIONEN AS s:WHERE s.GGUID = 0x" .
        $gamesAndActionsGguid . ";LinkAttribute='SUAGRP2SUAITEM')";
    if(!empty($customerGguid)) {
        $query .= " AND i.IsLinkedToWhere(ADDRESS AS a:WHERE a.GGUID = 0x" .
        $customerGguid . ";LinkAttribute='ITEM2KUNDE')";
    }
    $query .= ' ORDER BY TMNUMMER';

    $gwResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/query', [
        "query" => $query
    ]);

    if($gwResponse->failed()) {
        if($gwResponse->status() == 503) {
            return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error("Fehler beim Abrufen von getItemsForGamesAndActionsGguid (" . print_r($gamesAndActionsGguid,
                    true) .
                "): " .
            $query . "\n" . print_r
                ($gwResponse->body(), true));
            return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
        }
    }

    if(count(json_decode($gwResponse)) <= 0 || !property_exists(json_decode($gwResponse)[0], 'rows') || count(json_decode($gwResponse)[0]->rows) <= 0) {
        return [];
    }

    $items = json_decode($gwResponse)[0]->rows;
    return $items;
}


function getBingoCardByNumber($bingoCardNumber, $customerGguid) {

    $validator = Validator::make([
        'bingoCardNumber'     => $bingoCardNumber,
        'customerGguid'     => $customerGguid,
    ], [
        'bingoCardNumber'     => 'required',
        'customerGguid'     => 'required',
    ]);

    if ($validator->fails()) {
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'no_bingoCardNumber_or_customer',
            400);
    }

    return getSuaItemByField('GWAUTONUM', $bingoCardNumber, 'Spielkarte');
}

function getLotByNumber($lotNumber, $customerGguid) {

    $validator = Validator::make([
        'lotNumber'     => $lotNumber,
        'customerGguid'     => $customerGguid,
    ], [
        'lotNumber'     => 'required',
        'customerGguid'     => 'required',
    ]);

    if ($validator->fails()) {
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'no_lotsNumber_or_customer',
            400);
    }

    return getSuaItemByField('TMNUMMER', $lotNumber, 'Los', $customerGguid);
}

function getSuaItemByField($fieldName, $fieldValue, $gwsType) {

    $validator = Validator::make([
        'fieldName'     => $fieldName,
        'fieldValue'     => $fieldValue,
    ], [
        'fieldName'     => 'required|alpha_num',
        'fieldValue'     => 'required',
    ]);

    if ($validator->fails()) {
        return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'validation_failed',
            400);
    }

    $query = "SELECT * FROM SUAITEMS WHERE " . sanitize_text_field($fieldName) . " = '" . sanitize_text_field($fieldValue) . "' AND GWSTYPE = '" . sanitize_text_field($gwsType) . "'";

    $gwResponse = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->post(env('GW_API_BASE') . '/query', [
        "query" => $query
    ]);

    if($gwResponse->failed()) {
        if($gwResponse->status() == 503) {
            return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error("Fehler beim Abrufen von getSuaItemByField " . print_r($query, true) . "\n" . print_r
                ($gwResponse->body(), true));
            return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
        }
    }

    $responseData = json_decode($gwResponse);

    if(count($responseData) <= 0 || !property_exists($responseData[0], 'rows') || count($responseData[0]->rows) <= 0) {
        return createErrorObject('Es wurde kein Datensatz für diese Daten gefunden: ' . print_r(sanitize_text_field
            ($fieldName), true) . ', ' . print_r(sanitize_text_field
            ($fieldValue), true) . ', ' . print_r(sanitize_text_field
            ($gwsType), true),
            'no_suaitem_found', 400);
    }

    $bingoCardData = $responseData[0]->rows;

    if(count($bingoCardData) > 1) {
        return createErrorObject('Es wurden mehrere SUAITEMS Datensätze für diese Dateb gefunden.',
            'multiple_suaitems', 400);
    }

    $bingoCard = $bingoCardData[0];
    return $bingoCard;
}

function getLinkedPartnerForTransaction($linkedToTramsactionGguid) {
    return getLinkedObjects('ADDRESS', $linkedToTramsactionGguid, 'TRANSAKTIONSDATEN', 'ADRTADPARTNER');
}

function getLinkedPartnerForSuaItem($linkedToSuaItemGguid) {
    $response = getLinkedObjects('ADDRESS', $linkedToSuaItemGguid, 'SUAITEMS', 'ITEM2HERSTELLER');
    if(isError($response)) {
        return $response;
    } else {
        return $response[0]->fields;
    }
}
function getLinkedCustomerForSuaItem($linkedToSuaItemGguid) {
    return getLinkedObjects('ADDRESS', $linkedToSuaItemGguid, 'SUAITEMS', 'ITEM2KUNDE');
}

function getLinkedImageForSuaItem($linkedToSuaItemGguid) {
    return getLinkedObjects('DOCUMENT', $linkedToSuaItemGguid, 'SUAITEMS', 'AKTIONSIMG2ITEM');
}
function getLinkedGamesAndActionsForSuaItem($linkedToSuaItemGguid) {
    $response = getLinkedObjects('SPIELEUNDAKTIONEN', $linkedToSuaItemGguid, 'SUAITEMS', 'SUAGRP2SUAITEM');
    if(isError($response)) {
        return $response;
    } else {
        return $response[0]->fields;
    }
}
function getLinkedObjects($type, $linkedToGguid, $linkedToType, $linkedToAttributes, $orderBy = NULL) {

    $query = env('GW_API_BASE') . '/type/' . $type . '/full?linked-to=' . $linkedToGguid .
        '&linked-to-type=' . $linkedToType . '&linked-to-attributes=' . $linkedToAttributes;
    if(!empty($orderBy)) {
        $query .= '&order-by=' . $orderBy;
    }
    $getLinkedObjects = Http::withHeaders([
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . env("GW_AUTHORIZATION"),
        'X-CAS-PRODUCT-KEY' => 'Kundenportalintegration_REST+SOAP_B13A_rSU3yvHCvcXZDkhqaNkGsogfLwHg5H0+l+2N49fiUjZk505BBfyeo9UHun/cTnFSw9qnKk70WW9fXyS44xc3lA=='
    ])->get($query);

    if($getLinkedObjects->failed()) {
        if($getLinkedObjects->status() == 503) {
            return createErrorObject('Das System befindet sich gerade in Wartung oder ist nicht erreichbar. Bitte versuchen Sie es später erneut. Sollte das Problem bestehen bleiben, wenden Sie sich bitte an den Support.', 'system_maintenance', 503);
        } else {
            Log::error("Fehler beim Abrufen von getLinkedObjects: " . print_r($getLinkedObjects->body(), true));
            return createErrorObject('Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Support.', 'unknown_error', 500);
        }
    }

    $gwLinkedBranches = json_decode($getLinkedObjects);

    return $gwLinkedBranches;
}

function tmFormatCurrency($valueToFormat, $currency = 'EUR', $locale = 'de_DE') {
    $fmt = new NumberFormatter($locale, NumberFormatter::CURRENCY);
    $formattedValue = $fmt->formatCurrency($valueToFormat, $currency);
    if(str_contains($formattedValue, ',00')) {
        $formattedValue = str_replace(',00', '', $formattedValue);
    }
    if(str_contains($formattedValue, '.00')) {
        $formattedValue = str_replace('.00', '', $formattedValue);
    }
    return $formattedValue;
}

function tmFormatPercent($valueToFormat, $locale = 'de_DE') {
    $fmt = new NumberFormatter($locale, NumberFormatter::DECIMAL);
    $formattedValue = $fmt->format($valueToFormat);
    if(str_contains($formattedValue, ',00')) {
        $formattedValue = str_replace(',00', '', $formattedValue);
    }
    if(str_contains($formattedValue, '.00')) {
        $formattedValue = str_replace('.00', '', $formattedValue);
    }
    return $formattedValue . ' %';
}

function decryptURLGGUID($stringToDecrypt) {
    $decryptedGGUID = _decryptURLValue('potential_partners_registration_url_list', $stringToDecrypt);
    return $decryptedGGUID;
}

function _decryptURLValue($saltsConfigKey, $stringToDecrypt) {
    $secret = config('constants.secrets.' . $saltsConfigKey);
    $iv = config('constants.ivs.' . $saltsConfigKey);

    $hashedStringToDecrypt = hex2bin($stringToDecrypt);
    $decryptedString = openssl_decrypt($hashedStringToDecrypt, 'aes-256-cbc', $secret, 0, $iv);
    return $decryptedString;
}

function sanitize_text_field($input) {
    // Entfernt unsichtbare Zeichen (z.B. ASCII-Steuerzeichen)
    $input = preg_replace('/[\x00-\x1F\x7F]/', '', $input);

    // Entfernt HTML- und PHP-Tags
    $input = strip_tags($input);

    // Kürzt Leerzeichen am Anfang und Ende
    $input = trim($input);

    // Wandelt HTML-Entities um
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

    return $input;
}

Route::fallback(function() {
    return response()->json(['message' => 'Seite nicht gefunden. Wenn das Problem bestehen bleibt, kontaktieren Sie bitte support@trolleymaker.com'], 404);
});


