<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$host = !empty($_GET['host']) ? $_GET['host'] : '127.0.0.1';
$port = !empty($_GET['port']) ? $_GET['port'] : 6379;
$redis = new redis();
try{
    $redis->connect($host, $port);
}catch(Exception $e){
    echo '<style>:root { color-scheme: dark;}</style><pre>';
    echo "Exception:\n## {$e->getFile()}({$e->getLine()}): {$e->getMessage()}\n{$e->getTraceAsString()}\n";
    exit;
}
$param = [];
$search = '';
$query = '*';
if(isset($_GET['host'])) $param['host'] = $_GET['host'];
if(isset($_GET['port'])) $param['port'] = $_GET['port'];
if(!empty($_GET['search'])) {
    $search = $param['search'] = $_GET['search'];
    $query = "*$search*";
}
if(isset($_GET['act'])){
    $location = $_SERVER['DOCUMENT_URI'] . '?' . http_build_query($param);
    switch($_GET['act']){
    case 'flush':
        $redis->flushdb();
        header("Location: " . $location);
    case 'delete':
        if(isset($_GET['key'])) $redis->delete($_GET['key']);
        header("Location: " . $location);
    case 'set':
        if(isset($_GET['key']) && isset($_GET['value'])) {
            $option = empty($_GET['ttl']) ? [] : ['ex'=>$_GET['ttl']];
            $redis->set($_GET['key'], $_GET['value'], $option);
        }
        header("Location: " . $location);
    case 'get':
        if(isset($_GET['key'])){
            echo '<style>:root { color-scheme: dark;}</style><pre>';
            $key = $_GET['key'];
            if (!$redis->exists($key)) {
                echo "Key does not exist.";
                exit;
            }
            $tmp = $redis->get($key);
            $format = @$_GET['format'];
            if($format=='json' || empty($format)){
                $decode = json_decode($tmp);
                if($decode != null) {
                    echo "Format to json:\n\n".json_encode($decode, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    exit;
                }
                echo "json_decode result null\n\n";
            }
            if($format=='var_dump'){
                echo "var_dump:\n\n";
                var_dump($tmp);
                exit;
            }
            echo "Raw text:\n\n$tmp";
            exit;
        }
    }
}
$list = $redis->keys($query);
sort($list);
?>
<!DOCTYPE html>
<html data-bs-theme="dark">
<head>
    <title>Redis GUI</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body class="container">
    <h1>Redis GUI<a class="btn btn-light btn-sm" onclick="location.reload();">refresh</a></h1>
    <div class="container">
        <form action="?" method="get">
            <div class="row">
                <div>
                    <label for="host">host: </label><input type="text" id="host" name="host" value="<?=$host?>">
                    <label for="port">port: </label><input type="text" id="port" name="port" value="<?=$port?>">
                    <label for="search">search: </label><input type="text" id="search" name="search" value="<?=$search?>">
                    <input type="submit" value="connect" class="btn btn-sm btn-success">
                </div>
            </div>
        </form>
        <div class="row">
            <div class="col-5">Key</div>
            <div class="col-3">Value</div>
            <div class="col-3">TTL</div>
            <div class="col-1">Action</div>
        </div>
        <?php foreach($list as $k=>$v): ?>
        <div class="row mb-1">
            <div class="col-5"><?=$v ?></div>
            <div class="col-3">
                <a class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#cache<?=$k?>">view raw</a>
                len: <?=$redis->strlen($v)?>
                <div class="modal fade" id="cache<?=$k?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-scrollable modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><?=$v?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <?php $src="?act=get&key=$v&host=$host&port=$port"; $formats=['json','raw','var_dump']; foreach($formats as $f): ?>
                                <a class="btn btn-primary btn-sm" href="<?="$src&format=$f"?>" target="iframe_<?=$k?>"><?=$f?></a>
                                <?php endforeach; ?>
                                <iframe name="iframe_<?=$k?>" src="<?=$src?>" frameborder="0" style="width:100%; height: 80vh;" loading="lazy"></iframe>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-3"><?=$redis->TTL($v) ?></div>
            <div class="col-1"><a href="<?="?act=delete&key=$v&host=$host&port=$port"?>" class="btn btn-sm btn-danger">Delete</a></div>
        </div>
        <?php endforeach; ?>
        <form action="?" method="get">
            <input type="hidden" id="act" name="act" value="set" required>
            <div class="row">
                <div class="col-5"><input type="text" id="key" name="key" placeholder="key" required></div>
                <div class="col-3"><input type="text" id="value" name="value" placeholder="value"></div>
                <div class="col-3"><input type="text" id="ttl" name="ttl" placeholder="ttl"></div>
                <div class="col-1"><input type="submit" value="set" class="btn btn-sm btn-success"></div>
            </div>
        </form>
    </div>
    <a href="<?="?act=flush&host=$host&port=$port"?>" class="btn btn-primary mt-3">flush</a>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>