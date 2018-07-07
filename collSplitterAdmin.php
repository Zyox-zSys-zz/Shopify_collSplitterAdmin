<?php

(function () {

function jsonReq($url, $opts) {
  $opts = $opts ?: [];
  $headers = $opts['headers'] ?: [];
  $opts['method'] = $opts['method'] ?: ( isset($opts['data']) ? 'POST' : 'GET' );
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HEADER, false);
  if ($opts['method'] === 'POST') {
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data = $opts['data']);
    $headers[] = 'Content-Type: application/json';
    $headers[] = 'Content-Length: ' . strlen($data);
  } elseif ($opts['method'] === 'DELETE') {curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");}
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  try {$r = curl_exec($ch);}
  catch (Exception $e) {echo $r = $e;}
  curl_close($ch);
  return $r;
}

function splitterReq($url) {
  header('Content-Type: application/json');
  $data = ['headers' => ["X-Shopify-Access-Token: {$_SESSION['oauth']}"]];
  if (( $data['method'] = $_SERVER['REQUEST_METHOD']) === 'POST' ) {$data['data'] = file_get_contents('php://input');}
  $r = jsonReq('https://' . $_SESSION['shop'] . $url, $data);
  die($r);
}

if (isset($_GET['debug'])) {
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
  if (isset($_GET['debug']) && $_GET['debug'] === '0') {echo '<pre>' . var_export($_SESSION, true) . "\n\n" . var_export($_SERVER, true) . '</pre>';}
}

session_start();
$API = parse_ini_file(__DIR__ . '/ShopifyAPI.ini');

isset($_SESSION['shop']) && isset($_GET['url']) && splitterReq($_GET['url']);

if (!isset($_SESSION['shop'])) {
  
  !isset($_GET['hmac']) && die("<script>top.location.href='https://" . (
    isset($_GET['shop']) && preg_match('/^[a-zA-Z0-9\-]+.myshopify.com$/', $_GET['shop'])
    ? $_GET['shop']
    : "' + prompt('Enter a valid Shopify shop URL', 'shop_name.myshopify.com') + '"
  ) . "/admin/api/auth?api_key={$API['KEY']}';</script>");
  
  !isset($_GET['timestamp']) && die('Request parameter {timestamp} missing');
  (['timestamp'] < (time() - 24 * 60 * 60)) && die('Request parameter {timestamp} is older than a day');
  $hmac = $_GET['hmac'];
  unset($_GET['hmac']);
  foreach ($_GET as $key=>$val) $params[] = "$key=$val";
  asort($params);
  $params = implode('&', $params);
  ($hmac == hash_hmac('sha256', $params, $API['SHARED_SECRET'])) or die('Request parameter {hmac} is invalid');;
  
  !isset($_GET['code']) && die("<script>top.location.href='https://{$_GET['shop']}/admin/oauth/authorize?client_id={$API['KEY']}&scope=read_products,write_products&redirect_uri=" . urlencode('https://' . $_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME']) . "';</script>");
  
  $res = json_decode(jsonReq("https://{$_GET['shop']}/admin/oauth/access_token", ['data' => json_encode([
    'client_id' => $API['KEY'],
    'client_secret' => $API['SHARED_SECRET'],
    'code' => $_GET['code']
  ])]));
  $_SESSION['oauth'] = $res->access_token;
  $_SESSION['shop'] = $_GET['shop'];
  unset($_SESSION['install']);
  die("<script>top.location.href='https://{$_SESSION['shop']}/admin/apps/collection-splitter';</script>");
  
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


#collSplitter_display > :nth-child(1) { /* errorView */
  width: 100%;
}
#collSplitter_display > :nth-child(1) > div { /* error element */
  width: 100%;
  box-sizing: border-box;
  background-color: #a00;
  color: #fff;
  border-radius: 0.3rem;
  border: 1px solid #f8f8f8;
}

#collSplitter_display > :nth-child(3) { /* view */
  width: 100%;
  height: 100%;
}
#collSplitter_display > :nth-child(3) > div { /* collection */
  display: flex;
  flex-direction: row;
  box-sizing: border-box;
  width: 100%;
  border: 2px solid #1c2260;
  border-radius: 0.5rem;
}
#collSplitter_display > :nth-child(3) > div > * {margin: 0.5rem;}
#collSplitter_display > :nth-child(3) > div > span { /* collection fields */
  flex: auto;
  text-align: center;
}

#collSplitter_display > :nth-child(2) { /* spinner */
  width: 40px;
  height: 40px;
  background-color: #1c2260;
  margin: 100px auto;
  -webkit-animation: sk-rotateplane 1.2s infinite ease-in-out;
  animation: sk-rotateplane 1.2s infinite ease-in-out;
}


#collSplitter_bar {
  display: flex;
  flex-direction: row;
  width: 100%;
  min-height: 2rem;
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
}

#collSplitter_bar > input {width: 5rem;}


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
  <button>split</button>
  <button>delete</button>
  <span>max size:</span>
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
    
    adminURL: '<?php echo (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on" ? "https" : "http") . "://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]"; ?>?url=/admin/',
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
      let errEl = this.errorView.appendChild(document.createElement('div'));
      errEl.closeB = errEl.appendChild(document.createElement('button'));
      errEl.closeB.innerText = 'Dismiss';
      errEl.closeB.addEventListener('click', this.dismissError);
      errEl.appendChild(document.createTextNode(' ' + err));
      return err;
    },
    dismissError(ev) {
      let errEl = ev.target.parentNode;
      errEl.parentNode.removeChild(errEl);
    },
    
    showSpinner(show = true) {return this.spinner.style.display = show ? 'block' : 'none';},
    
    
    async fetchJSON(url, post) {
      if(!this.haveFetchSlot()) {return this.enqueueFetch(url, post);}
      this.fetchSlots--;
      return fetch(this.adminURL + escape(url), {
        method: post == null ? 'GET' : ( post === false ? 'DELETE' : 'POST' ),
        body: post ? JSON.stringify(post) : undefined,
        credentials: 'include',
        headers: this.headers
      }).then(res => this.gotFetch(res, url, post)).catch(this.error);
    },
    
    haveFetchSlot() {return this.fetchSlots > 0 /*&& this.queue.length === 0*/;},
    
    async enqueueFetch(url, post) {return new Promise((resolve, reject) => {
      this.queue.push([url, post, resolve]);
    })},
      
    async gotFetch(res, url, post) {
      if(res.status == 508) {return this.enqueueFetch(url, post);}
      this.fetchSlots++;
      if(!res.ok) {return this.error(res);}
      if(this.queue.length !== 0 && this.haveFetchSlot()) {
        let [url, post, resolve] = this.queue.shift();
        resolve(this.fetchJSON(url, post));
      }
      res = res.json();
      if(res.errors) (console.error(res.errors));
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
      return this.fetchJSON(type + '/count.json')
      .then(res => {
        let proms = [];
        for(let page = 1, pages = Math.ceil(res.count/250); page <= pages; page++) {
          proms.push(this.fetchJSON(type + '.json?limit=250&page=' + page));
        }
        return Promise.all(proms);
      })
    },
    
    renderCollections(res) {
      this.showSpinner(false);
      for(let typeIndex in this.collTypes) {
        let type = this.collTypes[typeIndex], typeTag = type.substr(0, type.indexOf('_')), collsSets = res[typeIndex];
        for(let collsSet of collsSets) {for(let coll of collsSet[type]) {
          this.view.appendChild(this.mkCollectionEl(coll, typeTag));
        }}
      }
      return res;
    },
    
    mkCollectionEl(coll, type) {
      let el = document.createElement('div'),
      chkB = el.appendChild(document.createElement('input'));
      el.id = 'shopifyCollection_' + coll.id;
      chkB.type = 'checkbox';
      chkB.coll = {
        id: coll.id,
        title: coll.title,
        type: coll.type = type,
        chkB: chkB
      };
      chkB.addEventListener('change', this.chkB_change);
      for(let i of ['title', 'handle', 'id', 'type']) {el.appendChild(document.createElement('span')).innerHTML = '<b>' + i + ': </b><a href="https://' + this.shopURL + '/admin/collections/' + coll.id + '" target="_blank">' +coll[i] + '</a>';}
      return el;
    },
    
    
    updPending(chkB) {
      if(chkB.checked) {
        this.pending['c_' + chkB.coll.id] = chkB.coll;
        chkB.parentNode.style.borderColor = '#f00';
        return true;
      } else {
        delete this.pending['c_' + chkB.coll.id];
        chkB.parentNode.style.borderColor = '#1c2260';
        return false;
      }
      //this.submitB.setAttribute('title', 'Split ' + Object.keys(this.pending).length + ' selected collections');
    },
    
    async splitColl(collId) {
      let maxSize = this.maxSize.value;
      return this.fetchJSON('collects.json?collection_id=' + collId).then(res => {
        let items = res.collects, subItems, t = 0, proms = [];
        while(t++, (subItems = items.splice(0, maxSize)).length !== 0) {
          proms.push(this.mkCollectionFrom(collId, subItems.map(this.extractProduct), t));
        }
        return Promise.all(proms);
      }).catch(this.error);
    },
    
    extractProduct(item) {return {product_id: item.product_id};},
    
    async mkCollectionFrom(collId, items, count) {
      return this.fetchJSON('custom_collections.json', {custom_collection: {
        title: this.pending['c_' + collId].title + '-' + count,
        collects: items
      }})
      .then(res => this.view.appendChild(this.mkCollectionEl(res.custom_collection, 'custom')));
    },
    
    async deleteColl(id) {
      return this.fetchJSON(this.pending['c_' + id].type + '_collections/' + id + '.json', false)
      .then(res => {
        let el = document.getElementById('shopifyCollection_' + id);
        el.parentNode.removeChild(el);
      }).catch(this.error);
    },
    
    
    chkB_change(ev) {collSplitter.updPending(ev.target);},
    
    bClick(ev) {
      let tar = ev.target, act = tar.innerText, proms = [];
      if(!confirm('Are you sure you want to ' + act + ' the selected collections?')) {return false;}
      tar.disabled = true;
      tar.innerText = 'processing...';
      for(let i in collSplitter.pending) {proms.push(collSplitter[act + 'Coll'](i.slice(2)));}
      Promise.all(proms).then(ret => {
        for(let i in collSplitter.pending) {
          let chkB = collSplitter.pending[i].chkB;
          chkB.checked = false;
          collSplitter.updPending(chkB);
        }
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
      this.refreshView();
      return this;
    }
    
  };
  
  if(collSplitter.debug) {window.Shopify_collSplitter = collSplitter;}
  //return collSplitter.init();
  window.addEventListener('load', function() {return collSplitter.init();});
  
})();

</script>

</body>
</html><!--
no license
Download @ https://github.com/Zyox-zSys/Shopify_collSplitterAdmin
Donate @ ko-fi.com/zyoxzsys
