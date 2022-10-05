<?php
$full_images = glob("./{,*/,*/*/,*/*/*/}*.{jpg,JPG}", GLOB_BRACE);
$tmb_images = glob("./{,*/,*/*/,*/*/*/}tmb_*.{jpg,JPG}", GLOB_BRACE);
$first_img = $full_images[0];
$actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="canonical" href="<?=$actual_link?>">
    <!--  Essential META Tags -->
    <meta property="og:locale" content="it_IT">
    <meta property="og:title" content="{title}">
    <meta property="og:type" content="article" />
    <meta property="og:image" content="<?=$first_img?>">
    <meta property="og:image:secure_url" content="<?=$first_img?>">
    <meta property="og:image:width" content="1039">
    <meta property="og:image:height" content="797">
    <meta property="og:url" content="<?=$actual_link?>">
    <meta name="twitter:card" content="summary_large_image">

    <!--  Non-Essential, But Recommended -->
    <meta property="og:description" content="Prenota l'horse booth di cavallo production per riempire la tua festa di foto e di divertimento! Visita il nostro sito o contattaci!">
    <meta property="og:site_name" content="Cavallo Production">
    <meta name="twitter:image:alt" content="horsebooth">
    <meta name="twitter:image" content="<?=$first_img?>">
    <title>{title}</title>
    <style>
        .modal-window {
            position: fixed;
            background-color: rgba(255, 255, 255, 0.25);
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
            width: 50vh;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            padding: 2em;
            background: white;
        }
        .modal-window header {
            font-weight: bold;
        }
        .modal-window h1 {
            font-size: 150%;
            margin: 0 0 15px;
        }

        .modal-close {
            color: #aaa;
            line-height: 50px;
            font-size: 80%;
            position: absolute;
            right: 0;
            text-align: center;
            top: 0;
            width: 70px;
            text-decoration: none;
        }
        .modal-close:hover {
            color: black;
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

        .modal-window > div {
            border-radius: 1rem;
        }

        .modal-window div:not(:last-of-type) {
            margin-bottom: 15px;
        }

        .btn {
            text-decoration: none;
        }
        .btn img {
            width: 100%;
            box-shadow: 0px 5px 3px 1px #807c7c;
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
            <div>
                <a href="#opener<?=$index?>" title="Close" class="modal-close">Close</a>
                <img src="<?=$this_full?>" width="100%" loading="lazy"  alt="<?=$this_full?>"/>
                <a href='<?=$this_full?>' class="image-element" download='zampina_<?=$download_name?>'>scarica</a>
            </div>
        </div>
    <?php } ?>
</div>
</body>
</html>
