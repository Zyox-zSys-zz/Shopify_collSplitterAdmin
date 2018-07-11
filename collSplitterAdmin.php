<?php

(function () {
    
function jsonReq($url, $opts) {
  $opts = $opts ?: [];
  $headers = isset($opts['headers']) ? $opts['headers']  : [];
  $method = isset($opts['method']) ? $opts['method'] : ( isset($opts['data']) ? 'POST' : 'GET' );
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HEADER, false);
  if ($method === 'POST') {
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['data']);
    $headers[] = 'Content-Type: application/json';
    $headers[] = 'Content-Length: ' . strlen($opts['data']);
  } elseif ($method === 'DELETE') {curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");}
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  try {$r = curl_exec($ch);}
  catch (Exception $e) {echo $r = $e;}
  curl_close($ch);
  return $r;
}

session_start();

//if (isset($_GET['debug'])) {
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
//if (isset($_GET['debug']) && $_GET['debug'] === 'prevars') {echo "<pre>\n\n" . var_export($_SESSION, true) . "\n\n" . var_export($_SERVER, true) . '</pre>';}
//}

if (isset($_GET['logout'])) {
  header('WWW-Authenticate: Basic realm="Shopify private API key (cancel to install as regular app)"');
  header('HTTP/1.0 401 Unauthorized');
  unset($_SESSION['shop']);
}

if (isset($_SESSION['shop'])) {if (isset($_GET['url'])) {
  
  header('Content-Type: application/json');
  $opts = ['headers' => [(substr($_SESSION['oauth'], 0, 5) === 'Basic' ? 'Authorization: ' : 'X-Shopify-Access-Token: ') . $_SESSION['oauth']]];
  if (( $opts['method'] = $_SERVER['REQUEST_METHOD']) === 'POST' ) {$opts['data'] = file_get_contents('php://input');}
  $r = jsonReq("https://$_SESSION[shop]$_GET[url]", $opts);
  die($r);
  
}} else {
  
  if(!isset($_GET['hmac'])) {
    
    if(!isset($_SERVER['PHP_AUTH_USER']) && !isset($_SERVER['HTTP_AUTHORIZATION']) && !isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {header('WWW-Authenticate: Basic realm="Shopify private API key (cancel to install as regular app)"');}
  
    if(isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {$_SERVER['HTTP_AUTHORIZATION'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];} // custom rule for x-mapp fcgi php in .htaccess: SetEnvIf Authorization .+ HTTP_AUTHORIZATION=$0
    if(isset($_SERVER['HTTP_AUTHORIZATION'])) {list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':', base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));}

    if(!empty($_SERVER['PHP_AUTH_USER'])) {
      (!isset($_GET['shop']) || !preg_match('/^[a-zA-Z0-9\-]+.myshopify.com$/', $_GET['shop'])) && die("<script>top.location.href='https://$_SERVER[PHP_AUTH_USER]:$_SERVER[PHP_AUTH_PW]@$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]?shop=' + prompt('Enter a valid Shopify shop URL', 'shop_name.myshopify.com');</script>");
      $_SESSION['shop'] = $_GET['shop'];
      $_SESSION['oauth'] = $_SERVER['HTTP_AUTHORIZATION'] ?: 'Basic ' . base64_encode("$_SERVER[PHP_AUTH_USER]:$_SERVER[PHP_AUTH_PW]");
      return;
    }
  
  }
  
  $API = parse_ini_file(__DIR__ . '/ShopifyAPI.ini');
  
  !isset($_GET['hmac']) && die("<script>top.location.href='https://" . (
    isset($_GET['shop']) && preg_match('/^[a-zA-Z0-9\-]+.myshopify.com$/', $_GET['shop'])
    ? $_GET['shop']
    : "' + prompt('Enter a valid Shopify shop URL for Shopify\'s initial request', 'shop_name.myshopify.com') + '"
  ) . "/admin/api/auth?api_key={$API['KEY']}';</script>");
  
  (!isset($_GET['timestamp']) || (['timestamp'] < (time() - 24 * 60 * 60))) && die('Request parameter {timestamp} is missing or older than a day');
  $hmac = $_GET['hmac'];
  unset($_GET['hmac']);
  foreach ($_GET as $k => $v) $params[] = "$k=$v";
  asort($params);
  $params = implode('&', $params);
  ($hmac == hash_hmac('sha256', $params, $API['SHARED_SECRET'])) or die('Request parameter {hmac} is invalid');;
  
  !isset($_GET['code']) && die("<script>top.location.href='https://{$_GET['shop']}/admin/oauth/authorize?client_id={$API['KEY']}&scope=read_products,write_products&redirect_uri=" . urlencode("https://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]") . "';</script>");
  
  $_SESSION['oauth'] = json_decode(jsonReq("https://{$_GET['shop']}/admin/oauth/access_token", ['data' => json_encode([
    'client_id' => $API['KEY'],
    'client_secret' => $API['SHARED_SECRET'],
    'code' => $_GET['code']
  ])]))->access_token;
  $_SESSION['shop'] = $_GET['shop'];
  die("<script>top.location.href='https://{$_SESSION['shop']}/admin/apps';</script>");

}

//if (isset($_GET['debug']) && $_GET['debug'] === 'vars') {echo '<pre>' . var_export($_SESSION, true) . "\n\n" . var_export($_SERVER, true) . '</pre>';}

})();

?><!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">
<title>Shopify Collection Splitter</title>

<style>
  
html, body {
  width: 100%;
  height: 100%;
  margin: 0;
  color: #212b36;
  background-color: #f4f6f8;
  font-family: Tahoma, Sans Serif;
}

a {
  cursor: pointer;
  color: #333;
  text-decoration: none;
}
a:visited {
  color: #444;
}
a:hover {
  color: #000;
  text-shadow: #1c2260 0px 1px;
}


#collSplitter_display { /* > :nth-child(3) { /* view */
  box-sizing: border-box;
  padding-top: 2rem;
  width: 100%;
  height: 100%;
  overflow-y: scroll;
}

#collSplitter_display > :nth-child(1) { /* errorView */
  width: 100%;
}
.Shopify_collSplitterErr { /* errEl */
  width: 100%;
  box-sizing: border-box;
  background-color: #a00;
  color: #fff;
  border-radius: 0.3rem;
  border: 1px solid #f8f8f8;
}

.Shopify_collection {
  display: flex;
  flex-direction: row;
  box-sizing: border-box;
  width: 100%;
  border: 2px solid #00084b;
  border-radius: 0.5rem;
}
.Shopify_collection > * {margin: 0.5rem;}
.Shopify_collection > span { /* collection fields */
  flex: 1 1;
  text-align: left;
}
.Shopify_collection > span:nth-child(4) {flex: 0.7 0.7;}
.Shopify_collection > span:nth-child(5) {flex: 0.5 0.5;}

#collSplitter_display > :nth-child(2) { /* spinner */
  width: 4rem;
  height: 4rem;
  background-color: #1c2260;
  margin: 8rem auto;
  -webkit-animation: sk-rotateplane 1.2s infinite ease-in-out;
  animation: sk-rotateplane 1.2s infinite ease-in-out;
}


#collSplitter_bar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  display: flex;
  flex-direction: row;
  width: 100%;
  height: 2rem;
  overflow: hidden;
}

#collSplitter_bar > * {
  height: 100%;
  min-height: 2rem;
  flex: auto;
  box-sizing: border-box;
}

#collSplitter_bar > button {
  cursor: pointer;
}

#collSplitter_bar > span {
  text-align: right;
  vertical-align: middle;
  line-height: 2rem;
  font-weight: bold;
  background-color: #f4f6f8;
}
#collSplitter_bar > span > span {float:right;}

#collSplitter_bar > input, #collSplitter_bar > span {width: 3rem;}


@-webkit-keyframes sk-rotateplane {
  0% { -webkit-transform: perspective(120px) }
  50% { -webkit-transform: perspective(120px) rotateY(180deg) }
  100% { -webkit-transform: perspective(120px) rotateY(180deg)  rotateX(180deg) }
}

@keyframes sk-rotateplane {
  0% { 
    transform: perspective(120px) rotateX(0deg) rotateY(0deg);
    -webkit-transform: perspective(120px) rotateX(0deg) rotateY(0deg) 
  } 50% { 
    transform: perspective(120px) rotateX(-180.1deg) rotateY(0deg);
    -webkit-transform: perspective(120px) rotateX(-180.1deg) rotateY(0deg) 
  } 100% { 
    transform: perspective(120px) rotateX(-180deg) rotateY(-179.9deg);
    -webkit-transform: perspective(120px) rotateX(-180deg) rotateY(-179.9deg);
  }
}

</style>

</head>
<body>

<div id="collSplitter_bar">
  <button>refresh</button>
  <button name="split">split</button>
  <button name="delete">delete</button>
  <span><span>:</span>max size</span>
  <input type="number" value="5">
</div>
<div id="collSplitter_display">
  <div></div>
  <div></div>
  <div></div>
</div>

<script>

(function Shopify_CollectionSplitter() {'use strict';
    
  Object.defineProperty(HTMLElement.prototype, 'rmTextChildren', {value: function() {
    for(let node of this.childNodes) {if(node.nodeType === 3) {this.removeChild(node);}}
    return this.childNodes;
  }});

  let collSplitter = {
    
    adminURL: '<?php echo /*(!isset($_SERVER["HTTPS"]) ? "http" : "https") .*/ "https://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]"; ?>?url=/admin/',
    shopURL: '<?php echo $_SESSION["shop"]; ?>',
    parallelFetches: 6,
    debug: <?php echo isset($_GET['debug']) ? 'true' : 'false'; ?>,
    collTypes: ['smart_collections', 'custom_collections'],
    
    display: document.getElementById('collSplitter_display'),
    bar: document.getElementById('collSplitter_bar'),
    
    pending: {},
    queue: [],
    
    
    log(...msg) {if(this.debug) {console.info(...msg);} return msg.length > 1 ? msg : msg[0];},
    error(err) {
      console.error(err);
      let errEl = document.createElement('div');
      errEl.className = 'Shopify_collSplitterErr';
      errEl.closeB = errEl.appendChild(document.createElement('button'));
      errEl.appendChild(document.createTextNode(' ' + err));
      errEl.closeB.innerText = 'Dismiss';
      errEl.closeB.addEventListener('click', collSplitter.dismissError);
      collSplitter.errorView.appendChild(errEl);
      return err;
    },
    dismissError(ev) {
      let errEl = ev.target.parentNode;
      errEl.parentNode.removeChild(errEl);
    },
    
    showSpinner(show = true) {return this.spinner.style.display = show ? 'block' : 'none';},
    
    
    async fetchJSON(url, post) {
      if(this.fetchSlots <= 0) {return new Promise((resolve, reject) => {this.queue.push([url, post, resolve]);});}
      this.fetchSlots--;
      return fetch(this.adminURL + escape(url), {
        method: post == null ? 'GET' : ( post === false ? 'DELETE' : 'POST' ),
        body: post ? JSON.stringify(post) : undefined,
        credentials: 'include',
        headers: this.headers
      }).then(res => this.gotFetch(res, url, post)).catch(this.error);
    },
    async gotFetch(res, url, post) {
      if(res.status == 508) {return this.enqueueFetch(url, post);}
      this.fetchSlots++;
      if(!res.ok) {return this.error(res);}
      if(this.queue.length !== 0 && this.fetchSlots > 0) {this.queue[0].pop()(this.fetchJSON(...this.queue.shift()));}
      res = res.json();
      if(res.errors) (this.error(res.errors));
      return res;
    },
    
    
    async refreshView() {
      let proms = [];
      this.view.innerHTML = '';
      this.showSpinner();
      for(let type of this.collTypes) {proms.push(this.getCollections(type));}
      return Promise.all(proms).then(this.renderCollections.bind(this)).catch(this.error);
    },
    
    async getCollections(type) {
      this.collCountQ = [];
      return this.fetchJSON(type + '/count.json')
      .then(res => {
        let proms = [];
        for(let page = 1, pages = Math.ceil(res.count/250); page <= pages; page++) {proms.push(this.fetchJSON(type + '.json?limit=250&page=' + page));}
        return Promise.all(proms);
      })
    },
    renderCollections(res) {
      this.showSpinner(false);
      for(let typeIndex in this.collTypes) {
        let type = this.collTypes[typeIndex], typeTag = type.substr(0, type.indexOf('_')), collsSets = res[typeIndex];
        for(let collsSet of collsSets) {for(let coll of collsSet[type]) {this.view.appendChild(this.mkCollectionEl(coll, typeTag));}}
      }
      return res;
    },
    
    mkCollectionEl(coll, type) {
      let el = document.createElement('div'), chkB = el.appendChild(document.createElement('input'));
      el.id = 'shopifyCollection_' + coll.id;
      el.className = 'Shopify_collection';
      chkB.type = 'checkbox';
      chkB.coll = this.mkCollData(coll);
      chkB.addEventListener('click', this.chkB_click);
      for(let dataLabel in chkB.coll) {
        let field = document.createElement('span'), fieldA = document.createElement('a');
        field.appendChild(document.createElement('span')).innerText = dataLabel + ': ';
        fieldA.innerText = chkB.coll[dataLabel], fieldA.target = '_blank', fieldA.href = 'https://' + this.shopURL + '/admin/collections/' + coll.id;
        field.appendChild(fieldA), el.appendChild(field);
      }
      this.getCollCount(coll.id, el.lastChild.lastChild);
      chkB.coll.sort_order = coll.sort_order;
      chkB.coll.type = coll.type;
      chkB.coll.chkB = chkB;
      return el;
    },
    mkCollData({title, handle, id}, type) {return ({title, handle, id, count:'...'});},
    async getCollCount(id, el) {return this.fetchSlots > 0
      ? this.fetchJSON('products/count.json?collection_id=' + id).then((res) => {
          el.innerText = res.count;
          if(this.collCountQ.length !== 0) {while(this.fetchSlots) {this.getCollCount(...this.collCountQ.shift());}}
        })
      : this.collCountQ.push([id, el]);
    },
    
    
    updPending(chkB) {
      chkB.parentNode.style.borderColor = chkB.checked
      ? (this.pending[chkB.coll.id] = chkB.coll, '#f00')
      : (delete this.pending[chkB.coll.id], this.defaultBorder);
    },
    
    
    async splitColl(collId) {
      let maxSize = this.maxSize.value;
      return this.fetchJSON('collects.json?collection_id=' + collId).then(res => {
        let items = res.collects, subItems, t = 0, proms = [];
        while(t++, (subItems = items.splice(0, maxSize)).length !== 0) {proms.push(this.mkCollectionFrom(collId, subItems.map(this.extractProduct), t));}
        return Promise.all(proms);
      }).catch(this.error);
    },
    extractProduct(item) {return {product_id: item.product_id};},
    async mkCollectionFrom(collId, items, number) {return this.fetchJSON('custom_collections.json', {custom_collection: {
      title: this.pending[collId].title + '-' + number,
      collects: items,
      sort_order: this.pending[collId].sort_order
    }}).then(res => this.view.appendChild(this.mkCollectionEl(res.custom_collection, 'custom')));},
    
    async deleteColl(id) {
      return this.fetchJSON(this.pending[id].type + '_collections/' + id + '.json', false)
      .then(res => {
        let el = document.getElementById('shopifyCollection_' + id);
        el.parentNode.removeChild(el);
      }).catch(this.error);
    },
    
    
    chkB_click(ev) {
      if(collSplitter.working) {return ev.preventDefault();};
      collSplitter.updPending(ev.target);
    },
    
    bClick(ev) {
      let tar = ev.target, act = tar.name, proms = [];
      if(!confirm('Are you sure you want to ' + act + ' the selected collections?')) {return false;}
      collSplitter.working = tar.disabled = true;
      tar.innerText = 'processing...';
      for(let i in collSplitter.pending) {proms.push(collSplitter[act + 'Coll'](i));}
      Promise.all(proms).then(ret => {
        for(let {chkB} of Object.values(collSplitter.pending)) {if(chkB.checked) {
          chkB.checked = false;
          collSplitter.updPending(chkB);
        }}
        delete collSplitter.working;
        tar.innerText = act;
        tar.disabled = false;
      }).catch(collSplitter.error);
    },
    
  
    init() {
      [this.errorView, this.spinner, this.view] = this.display.rmTextChildren();
      [this.refreshB, this.submitB, this.delB, , this.maxSize] = this.bar.rmTextChildren();
      this.fetchSlots = this.parallelFetches;
      this.headers = {'Content-Type': 'application/json'};
      this.refreshB.addEventListener('click', this.refreshView.bind(this));
      this.submitB.addEventListener('click', this.bClick);
      this.delB.addEventListener('click', this.bClick);
      this.refreshView().then(() => this.defaultBorder = this.view.firstChild.style.borderColor);
      return this;
    }
    
  };
  
  if(collSplitter.debug) {window.Shopify_collSplitter = collSplitter;}
  window.addEventListener('load', function() {return collSplitter.init();});
  
})();

</script>

</body>
</html><!--
no license
Download @ https://github.com/Zyox-zSys/Shopify_collSplitterAdmin
Donate @ ko-fi.com/zyoxzsys
