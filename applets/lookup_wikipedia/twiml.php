<?php
$CI =& get_instance();
$plugin = OpenVBX::$currentPlugin;
$plugin = $plugin->getInfo();
$plugin_url = base_url().'plugins/'.$plugin['dir_name'];
$next = AppletInstance::getDropZoneUrl('next');

require_once($plugin['plugin_path'].'/simple_html_dom.php');

function get_output($url) {
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_FOLLOWLOCATION => 1,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $url,
        CURLOPT_USERAGENT => 'Wikipedia SMS Widget/1.0 en-US wtran@twilio.com',
        CURLOPT_VERBOSE => 1
    ));
    $output = curl_exec($ch);
    curl_close($ch);

    return $output;
}

try {
    $body = trim($_REQUEST['Body']);
} catch(Exception $e) {
    // Empty SMS
    if(empty($body))
        $msg = 'You message was blank. Please type in what you want to look up.';

    die;
}

// Valid SMS
$output = get_output('http://en.wikipedia.org/wiki/'.urlencode(str_replace(' ', '_', $body)));
$dom = str_get_html($output);

// Invalid response from Wikipedia
if(!$dom) {
    $msg = 'Sorry. We could not find what you are looking for.';

// Valid response from Wikipedia
} else {
    // Alternative meanings found
    if(strpos($output, 'may refer to:') !== FALSE) {
        $link = $dom->find('#bodyContent ul li a[href^="/wiki/"]', 0);
        $output = get_output('http://en.wikipedia.org'.$link->href);
        $dom = str_get_html($output);
        $msg = $dom->find('#bodyContent p', 0)->plaintext;

    // No results found
    } else if(strpos($output, 'There were no results matching the query.') !== FALSE) {
        $msg = 'Sorry. We could not find what you are looking for.';

    // Result found
    } else if(strpos($output, 'This page was last modified')) {
        $msg = $dom->find('#bodyContent p', 0)->plaintext;

    // Exception
    } else {
        $msg = 'Sorry. We could not find what you are looking for.';
    }
}

// Clean the text
$msg = preg_replace(array('/(\/.*\/)/', '/[^\x20-\x7F]*/', '/\s*\(helpinfo\);*\s*/', '/\[[0-9]+\]/'), array('', '', '', ''), $msg);
$msg = wordwrap($msg, 160, '$$$', TRUE);
$msg = explode('$$$', $msg);
?>
<Response>
    <?php foreach($msg as $segment): ?>
    <Sms><?php echo $segment ?></Sms>
    <?php endforeach; ?>

    <Redirect><?php echo $next ?></Redirect>
</Response>
