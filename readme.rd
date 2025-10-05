# Anketor - Ki�iye �zel Siber G�venlik Raporlama

Anketor art�k her kat�l�mc�ya, verdi�i yan�tlara g�re ki�iselle�tirilmi� siber g�venlik geri bildirimi sa�layan bir platformdur. Web uygulamas� ve e-posta g�venli�i gibi tema ba�l�klar�na g�re sorular� kategorize eder, kat�l�mc�n�n g��l� y�nlerini ve geli�tirmesi gereken alanlar� madde madde raporlar.

## �ne ��kanlar
- Web ve e-posta g�venli�i kategorileri i�in tek ankette birden fazla b�l�m
- Kat�l�mc� ak��� sonunda otomatik ki�isel rapor (anonim token ile eri�im)
- OpenAI deste�i aktifse ki�iye �zel aksiyon �nerileri, aksi halde yerle�ik fallback tavsiyeler
- Admin panelinden kat�l�mc� ba��na rapor g�r�nt�leme ve e-posta davet takibi
- PDF/CSV/SVG kurum raporlar� h�l� mevcut; bireysel rapor bunlar�n tamamlay�c�s�d�r

## Kurulum
1. `config.sample.php` dosyas�n� `config.php` olarak kopyalay�n ve MySQL / mail / OpenAI bilgilerinizi girin.
2. `install.php` sayfas�n� �al��t�rd���n�zda veritaban� mevcut de�ilse otomatik olarak olu�turulur ve demo verisi y�klenir. Sonras�nda g�venlik i�in bu dosyay� kald�rmay� unutmay�n.
3. Varsay�lan y�netici hesab�:
   - E-posta: `admin@example.com`
   - �ifre: `YeniParola123!`
4. Giri� yapt�ktan sonra `Anketler > Kat�l�mc�lar` b�l�m�nden rapor linklerini test edebilirsiniz.

## Veritaban� Yap�s�
- `survey_questions` tablosunda her soru i�in `category_key` alan� bulunur. Ki�isel rapor kategorilerini bu anahtar �zerinden �retir.
- `survey_responses` ile `response_answers` kat�l�mc� yan�tlar�n� ta��r. Y�nlendirme token'� `survey_participants.token` alan�ndad�r.

### Demo Verisi
Kurulumdan sonra gelen �rnekler:
- Anket: **2025 Siber G�venlik Ki�isel De�erlendirme** (kategori anahtarlar�: `web_guvenligi`, `eposta_guvenligi`)
- Kat�l�mc� token'lar�: `sec-2025-a1`, `sec-2025-b2`, `phish-2024-a1`, `phish-2024-b2`
- Rapor �rne�i: `personal_report.php?response=1&token=sec-2025-a1`

## Y�netici Ak���
1. `dashboard.php` � �zet metrikler.
2. `survey_edit.php` � anket meta bilgisi (tarih, durum, kategori).
3. `survey_questions.php` � soru ekleme, kategori anahtar� giri� alan� ve AI �neri formu.
4. `participants.php` � kat�l�mc� listesi, davet maili g�nderme, �Raporu G�r� ba�lant�s�.
5. `survey_reports.php` � kurumsal PDF/CSV/SVG ��kt�lar�, trend k�yaslar� ve ak�ll� rapor.

## Kat�l�mc� Deneyimi
- Davet linki: `answer.php?id={survey_id}&token={token}` format�ndad�r.
- Anket tamamlan�nca kullan�c� `personal_report.php` sayfas�na y�nlendirilir; rapor ba�lant�s�n� saklayabilir.
- Rapor ekran�nda g��l� alanlar, geli�tirme alanlar� ve �nerilen aksiyonlar a��k�a listelenir. Admin giri�i yap�lm��sa panel navbar� g�r�n�r.

## Ki�isel Rapor Mant���
`SurveyService::generatePersonalReport()` kat�l�mc� yan�tlar�n� kategori baz�nda gruplay�p ortalama skor, �oktan se�meli tercih ve a��k u�lu notlar� bir araya getirir. `AIClient::generatePersonalAdvice()` modeli tan�ml�ysa bu �zetten anlaml� aksiyonlar �retir; aksi h�lde fallback metni kullan�l�r.

## Kurulum Sayfas� `install.php`
- Veritaban� yoksa olu�turur, `database.sql` demo verisini y�kler.
- `CREATE DATABASE` veya `USE` komutlar� bar�nd�ran sat�rlar hosting k�s�t�na tak�lmas�n diye otomatik atlan�r.
- Kurulum tamamland�ktan sonra admin paneline d�n�� butonu sunar.

## Geli�tirme Notlar�
- Yeni soru eklerken `category_key` alan�n� doldurmay� unutmay�n (�rne�in `web_guvenligi`, `eposta_guvenligi`).
- Ki�isel rapor ��kt�s�n� geni�letmek i�in `SurveyService::groupAnswersByCategory()` fonksiyonunda kategori bazl� hesaplamalar yap�lmaktad�r.
- AI entegrasyonu kapal�ysa `fallbackPersonalAdvice()` devreye girer.

Demo veri ile oynay�p ki�isel raporu deneyin; yeni kategoriler olu�turup farkl� alanlar i�in (�r. mobil uygulama g�venli�i, fiziksel g�venlik) an�nda yeni b�l�mler ekleyebilirsiniz.
