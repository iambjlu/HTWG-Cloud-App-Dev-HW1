<?php
/******************  頂端 PHP：API 區塊  ******************/
header('Content-Type: text/html; charset=utf-8');

// 如果是 AJAX（content-type 為 JSON 或帶 action 參數），就走 API 流程
$isApi = isset($_GET['action']) || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
if ($isApi) {
    header('Content-Type: application/json; charset=utf-8');
    // === SQLite ===
    $pdo = new PDO('sqlite:' . __DIR__ . '/travel.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 第一次執行自動建表
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE NOT NULL,
            name  TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS trips (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            destination TEXT NOT NULL,
            start_date TEXT NOT NULL,
            short_desc TEXT NOT NULL,
            long_desc TEXT NOT NULL,
            transport TEXT,
            accommodation TEXT,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ");

    // 讀取 JSON body
    $body = json_decode(file_get_contents('php://input'), true) ?: [];

    // ==== 路由 ====
    $act = $_GET['action'] ?? $body['action'] ?? '';
    try {
        switch ($act) {
            /* 1 註冊 / 登入 */
            case 'register':
                $email = trim($body['email'] ?? '');
                $name  = trim($body['name']  ?? '');
                if (!$email || !$name) throw new Exception('email & name required', 400);

                $u = $pdo->prepare('SELECT * FROM users WHERE email = ?');
                $u->execute([$email]);
                $user = $u->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    $ins = $pdo->prepare('INSERT INTO users(email,name) VALUES (?,?)');
                    $ins->execute([$email, $name]);
                    $user = ['id'=>$pdo->lastInsertId(),'email'=>$email,'name'=>$name];
                }
                echo json_encode(['ok'=>true,'user'=>$user]);
                break;

            /* 2 建立行程 */
            case 'create_trip':
                $req = array_map('trim', $body);           // 取出所有欄位
                ['email'=>$email,'title'=>$title,'destination'=>$dest,'start_date'=>$sd,
                 'short_desc'=>$short,'long_desc'=>$long] = $req;
                if (!$email||!$title||!$dest||!$sd||!$short||!$long) throw new Exception('missing fields',400);
                if (mb_strlen($short)>80) throw new Exception('short_desc ≤ 80 chars',400);

                $uid = $pdo->query("SELECT id FROM users WHERE email='$email'")->fetchColumn();
                if (!$uid) throw new Exception('user not found',404);

                $stmt = $pdo->prepare('INSERT INTO trips(user_id,title,destination,start_date,short_desc,long_desc,transport,accommodation)
                                        VALUES(?,?,?,?,?,?,?,?)');
                $stmt->execute([$uid,$title,$dest,$sd,$short,$long,$req['transport']??'', $req['accommodation']??'']);
                echo json_encode(['ok'=>true,'trip_id'=>$pdo->lastInsertId()]);
                break;

            /* 3a 行程清單 */
            case 'list_trips':
                $email = trim($_GET['email'] ?? '');
                $uid = $pdo->query("SELECT id FROM users WHERE email='$email'")->fetchColumn();
                $rows = $uid ? $pdo->query("SELECT id,title,start_date FROM trips WHERE user_id=$uid ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC) : [];
                echo json_encode(['trips'=>$rows]);
                break;

            /* 3b 行程細節 */
            case 'get_trip':
                $id = (int)($_GET['id'] ?? 0);
                $row = $pdo->query("SELECT t.*,u.email,u.name FROM trips t JOIN users u ON u.id=t.user_id WHERE t.id=$id")->fetch(PDO::FETCH_ASSOC);
                if (!$row) throw new Exception('not found',404);
                echo json_encode(['trip'=>$row]);
                break;
            
            case 'logout':
               //php刷新
                echo json_encode(['ok'=>true]);

            default:
                throw new Exception('unknown action',404);
        }
    } catch (Exception $e) {
        http_response_code($e->getCode()?:500);
        echo json_encode(['error'=>$e->getMessage()]);
    }
    exit;     // API 結束 
}

/******************  HTML + Vue   ******************/
?>
<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <title>Travel Planner</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <style>
    body{font-family:system-ui,-apple-system,sans-serif;margin:2rem;line-height:1.6}
    .card{border:1px solid #ddd;border-radius:10px;padding:16px;margin-bottom:16px;flex: 1;}
    .row{display:grid;grid-template-columns:130px 1fr;margin-bottom:6px}
    input,textarea{width:100%;padding:6px}
    button{padding:6px 12px;margin-top:4px;cursor:pointer}
    .link{color:#0a58ca;cursor:pointer;text-decoration:underline}
    .muted{color:#666;font-size:0.9em}
    .error{color:#b00020}
    .container {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
}
input,
textarea {
  width: 100%;
  padding: 6px;
  box-sizing: border-box;
}

.row {
  display: grid;
  grid-template-columns: 130px 1fr;
  gap: 8px;          
  margin-bottom: 6px;
}
  </style>
</head>
<body>
<div id="app">
  <!--about-->
<div class="container">
  <div class="card">
    <h1>Trip Planner</h1>
    <span><b>Team name</b> <br>a travel destination.</span><br><br>
    <span><b>Team member</b><br>Po-Chun<br>Cosma<br>Jia-Xuen</span><br><br>
    <span><b>Professor</b><br>Dr. Markus Eiglsperger</span><br><br>
  </div>

  <!-- 註冊 -->
  <div class="card" v-if="!loggedIn">
    <h2>Login or Auto Register</h2>
    <div class="row"><label>Email</label><input v-model="email"></div>
    <div class="row"><label>Name</label><input v-model="name"></div>
    <button @click="register">Go</button>
    <span class="muted" v-if="msg">{{msg}}</span>
    <div class="error" v-if="err">{{err}}</div>
  </div>

  <div class="card" v-if="loggedIn">
    <h2>Welcome, {{name}}</h2>
    <button @click="logout">Logout</button>
    <span class="muted" v-if="msg">{{msg}}</span>
    <div class="error" v-if="err">{{err}}</div>
  </div>
</div>
  
<div class="container">
  <!-- 建立行程 -->
  <div class="card" v-if="loggedIn">
    <h2>Create Trip</h2>
    <div class="row"><label>Title</label><input v-model="f.title"></div>
    <div class="row"><label>Destination</label><input v-model="f.destination"></div>
    <div class="row"><label>Starts on</label><input type="date" v-model="f.start_date"></div>
    <div class="row"><label>Short Description</label><input v-model="f.short_desc" maxlength="80"></div>
    <div class="row"><label>Long Description</label><textarea v-model="f.long_desc" rows="3"></textarea></div>
    <button @click="createTrip">Create</button>
  </div>

  <!-- 行程清單 -->
  <div class="card" v-if="loggedIn">
    <h2>My Trips</h2>
    <button @click="loadTrips">Refresh</button>
    <ul><li v-for="t in trips">
      <span class="link" @click="detail(t.id)">{{t.title}}</span> － <span class="muted">{{t.start_date}}</span>
    </li></ul>
    <span>{{tripmsg}}</span>
  </div>
</div>
  <!-- 行程細節 -->
  <div class="card" v-if="trip">
    <h2>{{trip.title}}</h2>
    <p><b>Traveler</b><br>{{trip.name}} ({{trip.email}})</p>
    <p><b>Destination</b><br>{{trip.destination}}</p>
    <p><b>Starts on</b><br>{{trip.start_date}}</p>
    <p><b>Short Description</b><br>{{trip.short_desc}}</p>
    <p><b>Long Description</b><br>{{trip.long_desc}}</p><br>
    <!-- <p v-if="trip.transport"><b>Transport:</b>{{trip.transport}}</p>
    <p v-if="trip.accommodation"><b>Accommodation:</b>{{trip.accommodation}}</p>
   -->
</div>

<!-- Vue 3 CDN -->
<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
<script>
const app = Vue.createApp({
  data() {
  return {
    email: localStorage.email || '',
    name: localStorage.name || '',
    msg: '', err: '', trips: [], trip: null,
    f: {title:'',destination:'',start_date:'',short_desc:'',long_desc:''},
    isLoggedIn: !!localStorage.email // 預設：如果 localStorage 有存，就算登入
  }
},
computed: {
  loggedIn() { this.loadTrips(); return this.isLoggedIn }
},
methods: {
  async register() {
    this.err='';
    const r = await fetch('?action=register',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({action:'register', email:this.email, name:this.name})
    });
    const d = await r.json();
    if(!r.ok){ this.err=d.error; return }

    // 註冊成功存進 localStorage改登入狀態
    localStorage.email=this.email;
    localStorage.name=this.name;
    this.isLoggedIn = true;

    this.msg='';
    this.loadTrips();

  },
  async createTrip(){
    this.err=''; this.msg='';
    const r = await fetch('?action=create_trip',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({...this.f, action:'create_trip', email:this.email})
    });
    const d = await r.json();
    if(!r.ok){ this.err=d.error; return }

    this.msg='Trip created';
    this.f={title:'',destination:'',start_date:'',short_desc:'',long_desc:''};
    this.loadTrips();
  },
  async loadTrips(){
    this.err=''; this.msg='';
    const r = await fetch(`?action=list_trips&email=${this.email}`);
    const d = await r.json();
    if(!r.ok){ this.err=d.error; return }
    this.trips = d.trips;
  },
  async detail(id){
    this.err=''; this.msg=''; this.trip=null;
    const r = await fetch(`?action=get_trip&id=${id}`);
    const d = await r.json();
    if(!r.ok){ this.err=d.error; return }
    this.trip = d.trip;
  },
  logout(){
    localStorage.removeItem('email');
    localStorage.removeItem('name');
    this.email='';
    this.name='';
    this.trip=null;
    this.isLoggedIn = false;
    this.msg='Logged out';
  }
}
});
app.mount('#app');
</script>
</body>
</html>
