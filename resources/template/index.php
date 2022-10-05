<?php
$full_images = glob("./{,*/,*/*/,*/*/*/}*.{jpg,JPG}", GLOB_BRACE);
$tmb_images = glob("./{,*/,*/*/,*/*/*/}tmb_*.{jpg,JPG}", GLOB_BRACE);
$first_img = $full_images[0];
$actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

// meta params to evaluate
$og_locale = 'it_IT';
$og_description = 'Prenota l\'horse booth di cavallo production per riempire la tua festa di foto e di divertimento! Visita il nostro sito o contattaci!';
$og_sitename = 'Cavallo Production';
$og_img_alt = 'Horsebooth'
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" integrity="sha512-xh6O/CkQoPOWDdYTDqeRdPCVd1SpvCA9XXcUnZS2FmJNp1coAFzvtCN9BmamE+4aHK8yyUHUSCcJHgXloTyT2A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link rel="canonical" href="<?=$actual_link?>">
        <!--  Essential META Tags -->
        <meta property="og:locale" content="<?=$og_locale?>">
        <meta property="og:title" content="{title}">
        <meta property="og:type" content="article" />
        <meta property="og:image" content="<?=$first_img?>">
        <meta property="og:image:secure_url" content="<?=$first_img?>">
        <meta property="og:image:width" content="500">
        <meta property="og:image:height" content="500">
        <meta property="og:url" content="<?=$actual_link?>">
        <meta name="twitter:card" content="summary_large_image">

        <!--  Non-Essential, But Recommended -->
        <meta property="og:description" content="<?=$og_description?>">
        <meta property="og:site_name" content="<?=$og_sitename?>">
        <meta name="twitter:image:alt" content="<?=$og_img_alt?>">
        <meta name="twitter:image" content="<?=$first_img?>">
        <title>{title}</title>
        <style>
            .modal-window {
                position: fixed;
                background-color: rgba(33, 33, 33, 0.90);
                top: 0;
                right: 0;
                bottom: 0;
                left: 0;
                z-index: 999;
                visibility: hidden;
                opacity: 0;
                pointer-events: none;
                transition: all 0.3s;
            }
            .modal-window:target {
                visibility: visible;
                opacity: 1;
                pointer-events: auto;
            }
            .modal-window > div {
                width: 100%;
                height: 100%;
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                padding: 2em;
                background: white;
                border-radius: 1rem;
                background-size: contain;
                background-repeat: no-repeat;
                background-position: center;
            }

            .action-bar {
                position: absolute;
                width: 100%;
                height: 10vh;
                background-color: rgba(33, 33, 33, 0.90);
                color: white;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }

            /* Demo Styles */
            html,
            body {
                height: 100%;
            }

            html {
                font-size: 18px;
                line-height: 1.4;
            }

            body {
                font-family: apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                font-weight: 600;
                background-image: linear-gradient(to right, #7f53ac 0, #657ced 100%);
                color: black;
            }

            a {
                color: inherit;
                text-decoration: none;
            }

            .img-container {
                display: grid;
                justify-content: center;
                align-items: center;
            }

            .img-container .interior {
                text-align: center;
            }

            .modal-window div:not(:last-of-type) {
                margin-bottom: 15px;
            }

            .btn {
                text-decoration: none;
            }

            .btn img {
                width: 100%;
                box-shadow: 0 5px 3px 1px #807c7c;
            }

            .container {
                display: grid;
                gap: 1rem;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                align-content: center;
                align-items: end;
                justify-content: center;
            }
        </style>
    </head>
    <body>
        <header>
            <h1>{title}</h1>
        </header>

        <div class="container">
            <?php $index = 0; ?>
            <?php foreach ($tmb_images as $filename) { ?>
                <?php
                $index += 1;
                $this_full = str_replace("tmb_", "", $filename);
                $path_array = explode('/', $this_full);
                $download_name = end($path_array);
                ?>
                <div class="img-container">
                    <div class="interior">
                        <a id="opener<?=$index?>" class="btn" href="#open-modal<?=$index?>"><img src="<?=$filename?>"  alt="<?=$filename?>"/></a>
                    </div>
                </div>
                <div id="open-modal<?=$index?>" class="modal-window">
                    <div class="modal-content" style="background: url('<?=$this_full?>')">
                        <div class="action-bar">
                            <a href="#opener<?=$index?>" title="Close"><i class="fa-solid fa-xmark"></i></a>
                            <a href='<?=$this_full?>' class="image-element" download='<?=$og_img_alt?>_<?=$download_name?>'><i class="fa-solid fa-download"></i></a>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </body>
</html>
