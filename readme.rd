# Anketor - Kiþiye Özel Siber Güvenlik Raporlama

Anketor artýk her katýlýmcýya, verdiði yanýtlara göre kiþiselleþtirilmiþ siber güvenlik geri bildirimi saðlayan bir platformdur. Web uygulamasý ve e-posta güvenliði gibi tema baþlýklarýna göre sorularý kategorize eder, katýlýmcýnýn güçlü yönlerini ve geliþtirmesi gereken alanlarý madde madde raporlar.

## Öne Çýkanlar
- Web ve e-posta güvenliði kategorileri için tek ankette birden fazla bölüm
- Katýlýmcý akýþý sonunda otomatik kiþisel rapor (anonim token ile eriþim)
- OpenAI desteði aktifse kiþiye özel aksiyon önerileri, aksi halde yerleþik fallback tavsiyeler
- Admin panelinden katýlýmcý baþýna rapor görüntüleme ve e-posta davet takibi
- PDF/CSV/SVG kurum raporlarý hâlâ mevcut; bireysel rapor bunlarýn tamamlayýcýsýdýr

## Kurulum
1. `config.sample.php` dosyasýný `config.php` olarak kopyalayýn ve MySQL / mail / OpenAI bilgilerinizi girin.
2. `install.php` sayfasýný çalýþtýrdýðýnýzda veritabaný mevcut deðilse otomatik olarak oluþturulur ve demo verisi yüklenir. Sonrasýnda güvenlik için bu dosyayý kaldýrmayý unutmayýn.
3. Varsayýlan yönetici hesabý:
   - E-posta: `admin@example.com`
   - Þifre: `YeniParola123!`
4. Giriþ yaptýktan sonra `Anketler > Katýlýmcýlar` bölümünden rapor linklerini test edebilirsiniz.

## Veritabaný Yapýsý
- `survey_questions` tablosunda her soru için `category_key` alaný bulunur. Kiþisel rapor kategorilerini bu anahtar üzerinden üretir.
- `survey_responses` ile `response_answers` katýlýmcý yanýtlarýný taþýr. Yönlendirme token'ý `survey_participants.token` alanýndadýr.

### Demo Verisi
Kurulumdan sonra gelen örnekler:
- Anket: **2025 Siber Güvenlik Kiþisel Deðerlendirme** (kategori anahtarlarý: `web_guvenligi`, `eposta_guvenligi`)
- Katýlýmcý token'larý: `sec-2025-a1`, `sec-2025-b2`, `phish-2024-a1`, `phish-2024-b2`
- Rapor örneði: `personal_report.php?response=1&token=sec-2025-a1`

## Yönetici Akýþý
1. `dashboard.php` – özet metrikler.
2. `survey_edit.php` – anket meta bilgisi (tarih, durum, kategori).
3. `survey_questions.php` – soru ekleme, kategori anahtarý giriþ alaný ve AI öneri formu.
4. `participants.php` – katýlýmcý listesi, davet maili gönderme, “Raporu Gör” baðlantýsý.
5. `survey_reports.php` – kurumsal PDF/CSV/SVG çýktýlarý, trend kýyaslarý ve akýllý rapor.

## Katýlýmcý Deneyimi
- Davet linki: `answer.php?id={survey_id}&token={token}` formatýndadýr.
- Anket tamamlanýnca kullanýcý `personal_report.php` sayfasýna yönlendirilir; rapor baðlantýsýný saklayabilir.
- Rapor ekranýnda güçlü alanlar, geliþtirme alanlarý ve önerilen aksiyonlar açýkça listelenir. Admin giriþi yapýlmýþsa panel navbarý görünür.

## Kiþisel Rapor Mantýðý
`SurveyService::generatePersonalReport()` katýlýmcý yanýtlarýný kategori bazýnda gruplayýp ortalama skor, çoktan seçmeli tercih ve açýk uçlu notlarý bir araya getirir. `AIClient::generatePersonalAdvice()` modeli tanýmlýysa bu özetten anlamlý aksiyonlar üretir; aksi hâlde fallback metni kullanýlýr.

## Kurulum Sayfasý `install.php`
- Veritabaný yoksa oluþturur, `database.sql` demo verisini yükler.
- `CREATE DATABASE` veya `USE` komutlarý barýndýran satýrlar hosting kýsýtýna takýlmasýn diye otomatik atlanýr.
- Kurulum tamamlandýktan sonra admin paneline dönüþ butonu sunar.

## Geliþtirme Notlarý
- Yeni soru eklerken `category_key` alanýný doldurmayý unutmayýn (örneðin `web_guvenligi`, `eposta_guvenligi`).
- Kiþisel rapor çýktýsýný geniþletmek için `SurveyService::groupAnswersByCategory()` fonksiyonunda kategori bazlý hesaplamalar yapýlmaktadýr.
- AI entegrasyonu kapalýysa `fallbackPersonalAdvice()` devreye girer.

Demo veri ile oynayýp kiþisel raporu deneyin; yeni kategoriler oluþturup farklý alanlar için (ör. mobil uygulama güvenliði, fiziksel güvenlik) anýnda yeni bölümler ekleyebilirsiniz.
