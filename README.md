# Integrasi SSO-Samarinda menggunakan Laravel

[![Latest Stable Version](https://poser.pugx.org/novay/sso-client/v/stable)](https://packagist.org/packages/novay/sso-client)
[![Total Downloads](https://poser.pugx.org/novay/sso-client/downloads)](https://packagist.org/packages/novay/sso-client)
[![Latest Unstable Version](https://poser.pugx.org/novay/sso-client/v/unstable)](https://packagist.org/packages/novay/sso-client)
[![License](https://poser.pugx.org/novay/sso-client/license)](https://packagist.org/packages/novay/sso-client)

Package ini berbasis pada [Simple PHP SSO skeleton](https://github.com/zefy/php-simple-sso) dan dibuat khusus agar dapat berjalan dan digunakan di framework Laravel.

Teknologi Single-sign-on (sering disingkat menjadi SSO) adalah teknologi yang mengizinkan pengguna jaringan agar dapat mengakses aplikasi dalam jaringan hanya dengan menggunakan satu akun pengguna saja. Teknologi ini sangat diminati, khususnya dalam jaringan yang sangat besar dan bersifat heterogen (di saat sistem operasi serta aplikasi yang digunakan oleh komputer adalah berasal dari banyak vendor, dan pengguna dimintai untuk mengisi informasi dirinya ke dalam setiap platform yang berbeda tersebut yang hendak diakses oleh pengguna). Dengan menggunakan SSO, seorang pengguna hanya cukup melakukan proses autentikasi sekali saja untuk mendapatkan izin akses terhadap semua layanan yang terdapat di dalam jaringan.

### Requirements
* Laravel 5.5+
* PHP 7.1+

### How it works?
Client visits Broker and unique token is generated. When new token is generated we need to attach Client session to his session in Broker so he will be redirected to Server and back to Broker at this moment new session in Server will be created and associated with Client session in Broker's page. When Client visits other Broker same steps will be done except that when Client will be redirected to Server he already use his old session and same session id which associated with Broker#1.

![flow](https://sso.samarindakota.go.id/img/flow.jpg)

# Installation

#### 1. Install Package

Install package ini menggunakan composer.
```shell
$ composer require novay/sso-client
```
Package ini otomatis akan mendaftarkan service provider kedalam aplikasi Anda.

#### 2. Publish Vendor

Salin file config `sso.php` ke dalam folder `config/` pada projek Anda dengan menjalankan:
```shell
$ php artisan vendor:publish --provider="Novay\SSO\Providers\SSOServiceProvider"
``` 
Berikut adalah isi konten default dari file konfigurasi yang disalin:
```php
//config/sso.php

return [
    'name' => 'Single Sign On - Broker (Client)', 
    'version' => '1.0.5', 

    /*
    |--------------------------------------------------------------------------
    | Redirect to ???
    |--------------------------------------------------------------------------
    | Arahkan kemana Anda akan tuju setelah login berhasil
    |
    */
    'redirect_to' => '/home', 

    /*
    |--------------------------------------------------------------------------
    | Konfigurasi auth.php
    |--------------------------------------------------------------------------
    | Pilih guard auth default yang dipakai
    |
    */
    'guard' => 'web', 

    /*
    |--------------------------------------------------------------------------
    | Pengaturan Umum untuk Broker
    |--------------------------------------------------------------------------
    | Beberapa parameter yang dibutuhkan untuk broker. Bisa ditemukan di
    | https://sso.samarindakota.go.id
    |
    */
    'server_url' => env('SSO_SERVER_URL', null),
    'broker_name' => env('SSO_BROKER_NAME', null),
    'broker_secret' => env('SSO_BROKER_SECRET', null),
    
    /*
    |--------------------------------------------------------------------------
    | Custom for UserList
    |--------------------------------------------------------------------------
    | Tentukan Model User yang dipakai
    |
    */
    'model' => '\App\Models\User'
];
```

#### 3. Edit Environment

Buat 3 opsi baru dalam file `.env` Anda:
```shell
SSO_SERVER_URL=https://sso.samarindakota.go.id
SSO_BROKER_NAME=
SSO_BROKER_SECRET=
```
`SSO_SERVER_URL` berisi URI dari SSO Samarinda. `SSO_BROKER_NAME` dan `SSO_BROKER_SECRET` harus diisi sesuai dengan data aplikasi yang didaftarkan di https://sso.samarindakota.go.id.

#### 4. Register Middleware

Edit file `app/Http/Kernel.php` dan tambahkan `\Novay\SSO\Http\Middleware\SSOAutoLogin::class` ke grup `web` middleware. Contohnya seperti ini:
```php
protected $middlewareGroups = [
	'web' => [
		...
	    \Novay\SSO\Http\Middleware\SSOAutoLogin::class,
	],

	'api' => [
		...
	],
];
```

Apabila dalam implementasinya Anda ingin melakukan penyimpanan sesi atau melakukan manipulasi pada models **User**, Anda juga bisa melakukan custom pada middleware yang telah disediakan. Contohnya:

a) Buat Middleware Baru

```shell
$ php artisan make:middleware SSOAutoLogin
```

b) Extend **Default Middleware** ke **Custom Middleware**

```php
<?php

namespace App\Http\Middleware;

use Novay\SSO\Http\Middleware\SSOAutoLogin as Middleware;
use App\Models\User;

class SSOAutoLogin extends Middleware
{
    /**
     * Manage your users models as your default credentials
     *
     * @param Broker $response
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleLogin($response)
    {
        $user = User::updateOrCreate(['uid' => $response['data']['id']], [
            'name' => $response['data']['name'], 
            'email' => $response['data']['email'], 
            'password' => 'default', 
        ]);

        auth()->login($user);

        return;
    }
}
```

c) Edit **Kernel.php**

```php
protected $middlewareGroups = [
    'web' => [
        ...
        // \Novay\SSO\Http\Middleware\SSOAutoLogin::class,
        \App\Http\Middleware\SSOAutoLogin::class,
    ],

    'api' => [
        ...
    ],
];
```

#### 5. Usage

a) Login

```html
<a href="{{ route('sso.authorize') }}">Login</a>
```

b) Logout

```html
<a href="{{ route('sso.logout') }}">Logout</a>
```

c) Manual Usage (Optional)

Untuk penggunaan secara manual, Anda bisa menyisipkan potongan script berikut kedalam fungsi login dan logout pada class controller Anda.
```php
protected function attemptLogin(Request $request)
{
    $broker = new \Novay\SSO\Services\Broker;
    
    $credentials = $this->credentials($request);
    return $broker->login($credentials['username'], $credentials['password']);
}

public function logout(Request $request)
{
    $broker = new \Novay\SSO\Services\Broker;
    $broker->logout();
    
    $this->guard()->logout();
    $request->session()->invalidate();
    
    return redirect('/');
}
```

d) Import User

```html
<a href="{{ route('sso.import', 'Service') }}">Import</a>
```

Tombol ini berfungsi untuk melakukan tarik data user guna melakukan inisiasi data user sesuai dengan tipenya. Adapun tipe yang tersedia adalah sebagai berikut:
- Service (Khusus User Unit Kerja)
- Guest (Khusus User Warga)
- Officer (Khusus User ASN atau Pegawai Pemkot)
- Company (Khusus User Perusahaan, Yayasan, Lembaga, LSM)

Adapun request yang diterima adalah sebagai berikut :
```
{
	...
	{
		'id' => 'UUID', 
		'name',  => 'Kecamatan Palaran', 
		'email',  => 'kec-palaran@samarindakota.go.id', 
		'type_id',  => 'None', 
		'number_id',  => 'XXX', 
		'jenis',  => 'Kecamatan', 
		'level' => 'Service', 
	}, 	
	...

}
```

Dan response yang diterima adalah sebagai berikut :
```
{
	'status' => 'success', 
        'message' => 'Daftar pengguna ('Service') berhasil diperbarui.', 
        'previous_url' => url()->previous()
}
```

Demikian. Untuk halaman Broker lain Anda harus mengulang semuanya dari awal hanya dengan mengubah nama dan secret Broker Anda di file konfigurasi.

Contoh tambahan pada file `.env`:
```shell
SSO_SERVER_URL=https://sso.samarindakota.go.id
SSO_BROKER_NAME=Situsku
SSO_BROKER_SECRET=XXXXXXXXXXXXXXXX
```

### Credit
* [Pemerintah Kota Samarinda](https://samarindakota.go.id).
* [Dinas Komunikasi dan Informatika Kota Samarinda](https://diskominfo.samarindakota.go.id).
* Bidang Aplikasi dan Layanan E-Government (Bidang 4)

### License
SSO-Samarinda for Laravel is licensed under the MIT license for both personal and commercial products. Enjoy!
