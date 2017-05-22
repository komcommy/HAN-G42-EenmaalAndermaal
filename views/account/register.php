<?php
require($_SERVER['DOCUMENT_ROOT'] . '/config/app.php');
include($_SERVER['DOCUMENT_ROOT'] . '/include/style.inc.php');
require($_SERVER['DOCUMENT_ROOT'] . '/config/database.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/app/getpost.php');

// Start a session
session_start();

// Check if user is already logged on. If yes, redirect to accountpage.
if (isset($_SESSION['username'])) {
    header("Location: index.php");
}

$vars = array();
if ($_SERVER['REQUEST_METHOD'] == "POST") {
    global $vars, $errors;
    $vars = getRealPOST();
    if (isset($vars['email-submit'])) {
        sendmail();
    } elseif (isset($vars['final-submit'])) {
        checkEmptyFields();
        checkDuplicates();
        checkAndHashPasswords();
        checksecretCode();
        if (checkNoErrors()) {
            saveData();
        }
    }
}

$username       = $vars['username'] ?? "";
$firstname      = $vars['firstname'] ?? "";
$lastname       = $vars['lastname'] ?? "";
$email          = $vars['email'] ?? "";
$secretcode     = $vars['secretcode'] ?? "";
$address1       = $vars['address1'] ?? "";
$address2       = $vars['address2'] ?? "";
$zipcode        = $vars['zipcode'] ?? "";
$city           = $vars['city'] ?? "";
$country        = $vars['country'] ?? "";
$birthday       = $vars['birthday'] ?? "";
$secretanswer   = $vars['secretanswer'] ?? "";

function checkEmptyFields()
{
    global $errors;
    global $vars;
    $errors['username']     = ($vars['username']     == "") ? "vul je gebruikersnaam in aub."   : '';
    $errors['firstname']    = ($vars['firstname']    == "") ? "vul je voornaam in aub."         : '';
    $errors['lastname']     = ($vars['lastname']     == "") ? "vul je achternaam in aub."       : '';
    $errors['email']        = ($vars['email']        == "") ? "vul je email in aub."            : '';
    $errors['secretcode']   = ($vars['secretcode']   == "") ? "vul je geheime code in aub."     : '';
    $errors['password1']    = ($vars['password1']    == "") ? "vul je wachtwoord in aub."       : '';
    $errors['password2']    = ($vars['password2']    == "") ? "vul je wachtwoord nog een keer in aub." : '';
    $errors['address1']     = ($vars['address1']     == "") ? "vul je adres in aub."            : '';
    $errors['zipcode']      = ($vars['zipcode']      == "") ? "vul je postcode in aub."         : '';
    $errors['city']         = ($vars['city']         == "") ? "vul je stad in aub."             : '';
    $errors['country']      = ($vars['country']      == "") ? "vul je land in aub."             : '';
    $errors['birthday']     = ($vars['birthday']     == "") ? "vul je geboorte datum in aub."   : '';
    $errors['secretanswer'] = ($vars['secretanswer'] == "") ? "vul je antwoord in aub."         : '';
}

function sendmail()
{
    global $vars;
    $secretCode     = uniqid();
    $subject        = "EenmaalAndermaal email activatiecode";
    $message        =
        "Uw geheime code is: " . $secretCode . " Vul deze code in op de registratiepagina.";
    $headers        = 'From: noreply@iproject42.icasites.nl';
    mail($vars['email'], $subject, $message, $headers);
    $_SESSION['secretCode'] = password_hash($secretCode, PASSWORD_DEFAULT);
}


function checkDuplicates()
{
    global $errors, $pdo;
    if (isset($vars['username']) and usernameValid()) {
        $username = $vars['username'];
        $stmt = $pdo->prepare("SELECT username FROM Users");
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

        foreach ($data as $d) {
            if ($d == $username) {
                $errors['username'] = "Deze gebruikersnaam bestaat al";
                break;
            }
        }
    }

    if (isset($vars['email'])) {
        $email = $vars['email'];
        $stmt = $pdo->prepare("SELECT email FROM Users");
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

        foreach ($data as $d) {
            if ($d == $email) {
                $errors['email'] = "Dit email adres bestaat al";
                break;
            }
        }
    }
}

function passValid()
{
    global $vars, $errors;
    if (preg_match("/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{5,}$/", $vars['password1'])) {
        return true;
    } else {
        $errors['password1'] = 'je wachtwoord moet 5 tekens of langer zijn en er moet minstens 1 hoofdletter en 1 speciale teken in zitten';
        print($vars['password1']);
        return false;
    }
}

function usernameValid()
{
    global $vars, $errors;
    if (strlen($vars['username']) >= 3 and strlen($vars['username']) <= 20) {
        return true;
    } else {
        $errors['username'] = "je gebruikersnaam moet tussen de 3 en 20 tekens lang zijn";
    }
}

function checkAndHashPasswords()
{
    global $vars, $errors;
    $password1 = $vars['password1'];
    $password2 = $vars['password2'];
    if ($password1 != $password2) {
        $errors['password1'] = "De wachtwoorden moeten gelijk zijn aan elkaar";
        $errors['password2'] = " ";
    } else if (passValid() === true) {
        $vars['hashedpassword'] = password_hash($password1, PASSWORD_DEFAULT);
    }
}

function checksecretCode()
{
    global $vars, $errors;
    if (password_verify($vars['secretcode'], $_SESSION['secretCode'])) {
        return true;
    } else {
        $errors['secretcode'] = "De code klopt niet, controleer of je de juiste code hebt ingevoerd";
    }
}

function saveData()
{
    global $vars, $pdo;
    $stmt = "INSERT INTO Users (username, firstname, lastname, address1, address2, zipcode, city, country, birthday, email, password, questionnumber, answer, merchant)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,0)";

    $processRegistration = $pdo->prepare($stmt);
    if ($processRegistration->execute([$vars['username'], $vars['firstname'], $vars['lastname'], $vars['address1'],
        $vars['address2'], $vars['zipcode'], $vars['city'], $vars['country'],
        $vars['birthday'], $vars['email'], $vars['hashedpassword'], $vars['sequrityquestion'],
        $vars['secretanswer']])
    ) {
        header('location: http://iproject42.icasites.nl/views/account/login.php');
    } else {
        print_r($processRegistration->errorInfo());
    }
}

function checkNoErrors()
{
    global $errors;
    foreach ($errors as $err) {
        if (!empty($err)) return false;
    }
    return true;
}

?>


<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <title><?=$title?></title>
    <link rel="stylesheet" href="<?=$app_url?>/assets/css/register.css">

</head>
<body>
    <div class="bg-overlay">
        <div class="container col-md-8 col-xs-6 jumbotron" style="background: rgba(236, 240, 241, 0.9);">
            <a href="<?=$app_url?>" class="btn btn-default" role="button" aria-pressed="true"><i class="fa fa-angle-double-left" aria-hidden="true"></i> Terug</a>
            <form class="form-horizontal sign-up-form" method="post" action="#">
                <div class="title">
                    <img src="<?=$cdn_url?>/storage/images/logo/logo-ea-groot-donker.png" style="max-height: 70px" alt="EenmaalAndermaal Logo">
                </div>

                <!-- Melding voor registreren -->
                <div class="alert alert-info" role="alert">
                    Heb je al een account? klik dan <a href="<?=$app_url?>/views/account/login.php"><strong>hier</strong></a> om in te loggen.
                </div>

                <!-- Foutmelding -->
                <?php
                if ($_SERVER['REQUEST_METHOD'] == "POST" and !checkNoErrors()) {
                    print("<div class='alert alert-danger'><strong>Oei!</strong> er ging iets mis tijdens het registreren, 
                            controleer en pas de rode velden aan en probeer het daarna opniew</div>");
                }
                ?>

                <!-- Blok titel -->
                <div class="col-md-12">
                    <h3 style="color: black">Account aanmaken</h3>
                </div>

                <!-- Invoeren gebruikersnaam -->
                <div <?php print((!empty($errors['username'])) ? 'class="form-group row has-danger"' : 'class="form-group row"'); ?> >
                    <div class="input-group inputform">
                        <span class="input-group-addon width50 fa fa-user"></span>
                        <input type="text" id="username" class="form-control" name="username"
                               placeholder="Gebruikersnaam" <?php print("value=\"$GLOBALS[username]\"") ?> autofocus>
                    </div>
                    <div class="form-control-feedback"><?php global $errors;
                        echo $errors['username'] ?></div>
                    <small class="form-text text-muted">Een unieke naam om mee in te loggen (3-20 alfanumerieke karakters lang)</small>
                </div>

                <!-- Invoeren voornaam -->
                <div <?php print((!empty($errors['firstname'])) ? 'class="form-group row has-danger"' : 'class="form-group row"'); ?>>
                    <div class="input-group inputform">
                        <span class="input-group-addon width50 fa fa-user" id="basicaddon1"></span>
                        <input type="text" id="firstname" class="form-control" name="firstname" placeholder="Voornaam"
                            <?php print("value=\"$GLOBALS[firstname]\"") ?> >
                    </div>
                    <!-- Foutmelding -->
                    <div class="form-control-feedback"><?php global $errors;
                        echo $errors['firstname'] ?>
                    </div>
                </div>

                <!-- Invoeren achternaam -->
                <div <?php print((!empty($errors['lastname'])) ? 'class="form-group row has-danger"' : 'class="form-group row"'); ?>>
                    <div class="input-group inputform">
                        <span class="input-group-addon width50 fa fa-user" id="basicaddon1"></span>
                        <input type="text" id="lastname" class="form-control" name="lastname" placeholder="Achternaam"
                            <?php print("value=\"$GLOBALS[lastname]\"") ?>>
                    </div>
                    <!-- Foutmelding -->
                    <div class="form-control-feedback"><?php global $errors;
                        echo $errors['lastname'] ?>
                    </div>
                </div>

                <!-- Invoeren geboortedatum -->
                <div <?php print((!empty($errors['birthday'])) ? 'class="form-group row has-danger"' : 'class="form-group row"'); ?>>
                    <!-- WTF is het probleem? Ergens glitcht er een stijlding... ^JW -->
                    <div class="input-group">
                        <span class="input-group-addon fa fa-calendar" id="basicaddon1"></span>
                        <input class="form-control" type="date" placeholder="Wat is uw geboortedatum" name="birthday" value="<?php if(isset($_POST['birthday'])){ echo $_POST['birthday'];}?>" id="example-date-input">
                    </div>
                    <!-- Foutmelding -->
                    <div class="form-control-feedback"><?php global $errors;
                        echo $errors['birthday'] ?>
                    </div>
                </div>

                <!-- Invoeren email, verifiëren unieke code per mail -->
                <div <?php print((!empty($errors['email'])) ? 'class="form-group row has-danger"' : 'class="form-group row"'); ?>>
                    <div class="input-group inputform">
                        <span class="input-group-addon width50 fa fa-envelope" id="basicaddon1"></span>
                        <input type="email" id="email" class="form-control" name="email" placeholder="E-Mail Adres"
                            <?php print("value=\"$GLOBALS[email]\"") ?> required>
                    </div>
                </div>

                <hr>

                <!-- Knop 'Code aanvragen' -->
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group">
                            <button type="submit" name="email-submit" id="email-submit"
                                    class="btn btn-success btn-block" style="margin: auto">Verificatiecode aanvragen
                            </button>
                        </div>

                        <small class="form-text text-muted">U dient eerst uw emailadres te verifiëren alvorens u verder kan gaan met het registratieproces. Vul hieronder de ontvangen geheime code in:
                        </small>
                        <!-- Foutmelding -->
                        <div class="form-control-feedback"><?php global $errors;
                            echo $errors['email'] ?>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div <?php print((!empty($errors['secretcode'])) ? 'class="form-group row has-danger"' : 'class="form-group row"'); ?>>
                            <div class="input-group inputform">
                                <span class="input-group-addon fa fa-lock" id="basicaddon1"></span>
                                <input type="text" id="secretcode" class="form-control" name="secretcode" placeholder="Geheime code"
                                    <?php print("value=\"$GLOBALS[secretcode]\"") ?>>
                            </div>
                            <!-- Foutmelding -->
                            <div class="form-control-feedback"><?php global $errors;
                                echo $errors['secretcode'] ?></div>
                            <small class="form-text text-muted">Niet ontvangen? Check uw spambox of verstuur opnieuw.
                            </small>
                        </div>
                    </div>
                </div>



                <hr>

                <!-- Blok titel -->
                <div class="col-md-12">
                    <h3 style="color: black">Wachtwoord</h3>
                </div>

                <!-- Div row - wachtwoord -->
                <div class="row">
                    <small class="form-text text-muted">Een wachtwoord moet minstens 1 hoofdletter, 1 kleine letter, 1 getal
                        en 1 speciaal teken bevatten en moet minstens 5 tekens lang zijn
                    </small>
                    <!-- Invoeren wachtwoord1 -->
                    <div class="col-md-6">
                        <div <?php print((!empty($errors['password1'])) ? 'class="form-group row has-danger"' : 'class="form-group row"'); ?>>
                            <div class="input-group inputform">
                                <span class="input-group-addon fa fa-lock" id="basicaddon1"></span>
                                <input type="password" id="password1" class="form-control" name="password1"
                                       placeholder="Wachtwoord">
                            </div>
                            <!-- Foutmelding -->
                            <div class="form-control-feedback"><?php global $errors;
                                echo $errors['password1'] ?>
                            </div>
                        </div>
                    </div>

                    <!-- Invoeren wachtwoord2 (ter controle) -->
                    <div class="col-md-6">
                        <div <?php print((!empty($errors['password2'])) ? 'class="form-group row has-danger"' : 'class="form-group row"'); ?>>
                            <div class="input-group inputform">
                                <span class="input-group-addon fa fa-lock" id="basicaddon1"></span>
                                <input type="password" id="password2" class="form-control" name="password2"
                                       placeholder="Herhaal wachtwoord">
                            </div>
                            <!-- Foutmelding -->
                            <div class="form-control-feedback"><?php global $errors;
                                echo $errors['password2'] ?>
                            </div>
                        </div>
                    </div>
                </div>

                <hr>

                <!-- Blok titel -->
                <div class="col-md-12">
                    <h3 style="color: black">Adresgegevens</h3>
                </div>

                <!-- Invoeren adres -->
                <div <?php print((!empty($errors['address1'])) ? 'class="form-group row has-danger"' : 'class="form-group row"'); ?>>
                    <div class="input-group inputform">
                        <span class="input-group-addon fa fa-map-marker" id="basicaddon1"></span>
                        <input type="text" id="address1" class="form-control" name="address1" placeholder="Adres"
                            <?php print("value=\"$GLOBALS[address1]\"") ?>>
                    </div>
                    <!-- Foutmelding -->
                    <div class="form-control-feedback"><?php global $errors;
                        echo $errors['address1'] ?>
                    </div>
                </div>

                <!-- Invoeren adres2 (veelal voor buitenland) -->
                <div class="form-group row">
                    <div class="input-group inputform">
                        <span class="input-group-addon fa fa-map-marker" id="basicaddon1"></span>
                        <input type="text" id="address2" class="form-control" name="address2"
                               placeholder="Adres (optioneel)"
                            <?php print("value=\"$GLOBALS[address2]\"") ?>>
                    </div>
                </div>

                <!-- Invoeren postcode -->
                <div <?php print((!empty($errors['zipcode'])) ? 'class="form-group row has-danger"' : 'class="form-group row"'); ?>>
                    <div class="input-group inputform">
                        <span class="input-group-addon fa fa-map-marker" id="basicaddon1"></span>
                        <input type="text" id="zipcode" class="form-control" name="zipcode" placeholder="Postcode"
                            <?php print("value=\"$GLOBALS[zipcode]\"") ?>>
                    </div>
                    <!-- Foutmelding -->
                    <div class="form-control-feedback"><?php global $errors;
                        echo $errors['zipcode'] ?>
                    </div>
                </div>

                <!-- Invoeren stad -->
                <div <?php print((!empty($errors['city'])) ? 'class="form-group row has-danger"' : 'class="form-group row"'); ?>>
                    <div class="input-group inputform">
                        <span class="input-group-addon fa fa-map-marker" id="basicaddon1"></span>
                        <input type="text" id="city" class="form-control" name="city" placeholder="Plaatsnaam"
                            <?php print("value=\"$GLOBALS[city]\"") ?>>
                    </div>
                    <!-- Foutmelding -->
                    <div class="form-control-feedback"><?php global $errors;
                        echo $errors['city'] ?>
                    </div>
                </div>

                <!-- Keuzeveld landen, NL default -->
                <div <?php print((!empty($errors['country'])) ? 'class="form-group row has-danger"' : 'class="form-group row"'); ?>>
                    <div class="input-group inputform">
                        <span class="input-group-addon fa fa-globe" id="basicaddon1"></span>
                        <select class="form-control" id="country" name="country">
                            <?php
                            $stmt = $pdo->prepare("SELECT * FROM Country");
                            $stmt->execute();
                            $data = $stmt->fetchAll();
                            echo "<option>Netherlands</option>";
                            foreach ($data as $row) { ?>
                                <option><?php echo $row['countryname'] ?></option>
                                <?php
                            }
                            ?>
                        </select>
                    </div>
                    <!-- Foutmelding -->
                    <div class="form-control-feedback"><?php global $errors;
                        echo $errors['country'] ?>
                    </div>
                </div>

                <hr>

                <!-- Blok titel -->
                <div class="col-md-12">
                    <h3 style="color: black">Geheime vraag</h3>
                </div>

                <!-- Tonen geheime vraag -->
                <div class="form-group row">
                    <div class="input-group inputform">
                        <span class="input-group-addon fa fa-cog" id="basicaddon1"></span>
                        <select class="form-control" id="securityquestion" name="sequrityquestion">
                            <?php
                            $stmt = $pdo->prepare("SELECT * FROM Question");
                            $stmt->execute();
                            $data = $stmt->fetchAll();
                            foreach ($data as $row) {
                                print("<option value=\"$row[0]\">$row[1]</option>");
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <!-- Invoeren geheim antwoord -->
                <div <?php print((!empty($errors['secretanswer'])) ? 'class="form-group row has-danger"' : 'class="form-group row"'); ?>>
                    <div class="input-group inputform">
                        <span class="input-group-addon fa fa-cog" id="basicaddon1"></span>
                        <input type="text" id="secretanswer" class="form-control" name="secretanswer"
                               placeholder="Antwoord..."
                            <?php print("value=\"$GLOBALS[secretanswer]\"") ?>>
                    </div>
                    <!-- Foutmelding -->
                    <div class="form-control-feedback"><?php global $errors;
                        echo $errors['secretanswer'] ?>
                    </div>
                </div>

                <br>
                <div class="form-group row">
                    <button type="submit" class="btn btn-success btn-lg btn-block" id="final-submit" name="final-submit"
                            value="finished" style="margin:auto;">Registreren
                    </button>
                </div>
            </form>
        </div>
        <br>
        <br>
    </div>
</body>
</html>