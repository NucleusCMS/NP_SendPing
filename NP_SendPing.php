<?php
class NP_SendPing extends NucleusPlugin {
    function getName()  { return 'SendPing'; }
    function getAuthor() { return 'Tokitake'; }
    function getURL()     { return 'http://www.fukulog.com/'; }
    function getVersion() { return '0.7'; }
    function getDescription() {
        return 'This plugin will send XML-RPC pings';
    }
    
    function getDebug() { return 0; }
    
   function supportsFeature($what) { 
      switch($what){ 
         case 'SqlTablePrefix': 
            return 1; 
         default: 
            return 0; 
      } 
   }
   
	function getMinNucleusVersion() {
		return 220;
	}

   	function install() {
		$this->createBlogOption('pingurl','Ping URL','textarea','');
		$this->createBlogOption('updateurl','Update URL','text','');
	}

   	function uninstall() {
		$this->deleteBlogOption('pingurl');
		$this->deleteBlogOption('updateurl');
	}


    function getEventList() {
        return array('PreAddItem','PostAddItem','AddItemFormExtras','EditItemFormExtras','PreUpdateItem');
    }

	function event_AddItemFormExtras($data) {
		?>
			<h3>Ping</h3>

			<p>
				<label for="plug_ping_check">Send Ping ?:</label>
				<input type="checkbox" value="1" id="plug_ping_check" name="plug_ping_check" checked /><br />
			</p>
		<?php
	}

	function event_EditItemFormExtras($data) {
		?>
			<h3>Ping</h3>

			<p>
				<label for="plug_ping_check">Send Ping ?:</label>
				<input type="checkbox" value="1" id="plug_ping_check" name="plug_ping_check" /><br />
			</p>
		<?php
	}

    function event_PreAddItem($data) {
        $this->myBlogId    = $data['blog']->blogid;
        $this->myPostTitle = $data['title'];
    }

    function sendPing($pingurls) {
		$b = new BLOG($this->myBlogId);
		$pingurl = preg_split ("/[\s,]+/", $pingurls);
		$updateurl = $this->getBlogOption($this->myBlogId, 'updateurl');
		if($updateurl == ""){
			$updateurl = $b->getURL();
		}
		foreach ($pingurl as $target){
			$url = parse_url ($target);
			$name = $b->getName();

			if (_CHARSET != 'UTF-8') {
				$name = mb_convert_encoding($name, "UTF-8", _CHARSET);
			}

			$ping_info = new xmlrpcmsg(
				'weblogUpdates.ping', 
					array(
					new xmlrpcval($name, 'string'),
					new xmlrpcval($updateurl, 'string')
					)
			);
			$ping_target = new xmlrpc_client($url[path], $url[host], 80);
			$response = $ping_target->send($ping_info,20);
			if (!$response) {
				ACTIONLOG::add(WARNING, 'Ping Error:' . $target . " - " . 'Could not connect to HTTP server.');
			} elseif ($response->faultCode()) {
				ACTIONLOG::add(WARNING, 'Ping Error:' . $target . " - " . $response->faultCode() . ": " . $response->faultString());
			} elseif( $this->getDebug() == 1) {
			    $struct = $response->value();
			    $resultval =  $struct->structmem('message');
				ACTIONLOG::add(WARNING, 'Ping Message:' . $target . " - Results:" . $resultval->scalarval());
			}
		}
    }
 
    
    function event_PostAddItem($data) {
    	global $manager, $DIR_LIBS;
    	if($this->getBlogOption($this->myBlogId, 'pingurl') != '' && requestVar('plug_ping_check') == 1){
			$itemid = $data['itemid'];
			$item =& $manager->getItem($itemid, 0, 0);
			if (!$item) return; // don't ping for draft & future
			if ($item['draft']) return;	// don't ping on draft items
			if (!class_exists(xmlrpcmsg)) include($DIR_LIBS . "xmlrpc.inc.php");
	        $this->sendPing($this->getBlogOption($this->myBlogId, 'pingurl'));
	    }
	    return;
    }
    
     function event_PreUpdateItem($data) {
    	global $manager, $DIR_LIBS;
        $this->myBlogId    = $data['blog']->blogid;
        $this->myPostTitle = $data['title'];
    	if($this->getBlogOption($this->myBlogId, 'pingurl') != '' && requestVar('plug_ping_check') == 1){
			$itemid = $data['itemid'];
			$item =& $manager->getItem($itemid, 0, 0);
			if (!$item) return; // don't ping for draft & future
			if ($item['draft']) return;	// don't ping on draft items
			if (!class_exists(xmlrpcmsg)) include($DIR_LIBS . "xmlrpc.inc.php");
	        $this->sendPing($this->getBlogOption($this->myBlogId, 'pingurl'));
	    }
	    return;
    }
}
?>