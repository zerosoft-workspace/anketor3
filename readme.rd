# Anketor - Kişiye Özel Siber Güvenlik Raporlama

Anketor artık her katılımcıya, verdiği yanıtlara göre kişiselleştirilmiş siber güvenlik geri bildirimi sağlayan bir platformdur. Web uygulaması ve e-posta güvenliği gibi tema başlıklarına göre soruları kategorize eder, katılımcının güçlü yönlerini ve geliştirmesi gereken alanları madde madde raporlar.

## Öne Çıkanlar
- Web ve e-posta güvenliği kategorileri için tek ankette birden fazla bölüm
- Katılımcı akışı sonunda otomatik kişisel rapor (anonim token ile erişim)
- OpenAI desteği aktifse kişiye özel aksiyon önerileri, aksi halde yerleşik fallback tavsiyeler
- Admin panelinden katılımcı başına rapor görüntüleme ve e-posta davet takibi
- PDF/CSV/SVG kurum raporları hâlâ mevcut; bireysel rapor bunların tamamlayıcısıdır

## Kurulum
1. `config.sample.php` dosyasını `config.php` olarak kopyalayın ve MySQL / mail / OpenAI bilgilerinizi girin.
2. `install.php` sayfasını çalıştırdığınızda veritabanı mevcut değilse otomatik olarak oluşturulur ve demo verisi yüklenir. Sonrasında güvenlik için bu dosyayı kaldırmayı unutmayın.
3. Varsayılan yönetici hesabı:
   - E-posta: `admin@example.com`
   - Şifre: `YeniParola123!`
4. Giriş yaptıktan sonra `Anketler > Katılımcılar` bölümünden rapor linklerini test edebilirsiniz.

## Veritabanı Yapısı
- `survey_questions` tablosunda her soru için `category_key` alanı bulunur. Kişisel rapor kategorilerini bu anahtar üzerinden üretir.
- `survey_responses` ile `response_answers` katılımcı yanıtlarını taşır. Yönlendirme token'ı `survey_participants.token` alanındadır.

### Demo Verisi
Kurulumdan sonra gelen örnekler:
- Anket: **2025 Siber Güvenlik Kişisel Değerlendirme** (kategori anahtarları: `web_guvenligi`, `eposta_guvenligi`)
- Katılımcı token'ları: `sec-2025-a1`, `sec-2025-b2`, `phish-2024-a1`, `phish-2024-b2`
- Rapor örneği: `personal_report.php?response=1&token=sec-2025-a1`

## Yönetici Akışı
1. `dashboard.php` – özet metrikler.
2. `survey_edit.php` – anket meta bilgisi (tarih, durum, kategori).
3. `survey_questions.php` – soru ekleme, kategori anahtarı giriş alanı ve AI öneri formu.
4. `participants.php` – katılımcı listesi, davet maili gönderme, “Raporu Gör” bağlantısı.
5. `survey_reports.php` – kurumsal PDF/CSV/SVG çıktıları, trend kıyasları ve akıllı rapor.

## Katılımcı Deneyimi
- Davet linki: `answer.php?id={survey_id}&token={token}` formatındadır.
- Anket tamamlanınca kullanıcı `personal_report.php` sayfasına yönlendirilir; rapor bağlantısını saklayabilir.
- Rapor ekranında güçlü alanlar, geliştirme alanları ve önerilen aksiyonlar açıkça listelenir. Admin girişi yapılmışsa panel navbarı görünür.

## Kişisel Rapor Mantığı
`SurveyService::getParticipantResponses($participantId)` ilgili kişinin son yanıt paketini (soru, seçenek ve kategori anahtarıyla) döndürür. Bu veri `SurveyService::generatePersonalReport(array $bundle)` ile işlenerek kategori bazlı özet, ortalama skor ve aksiyon önerisi üretir. `AIClient::generatePersonalAdvice()` modeli tanımlıysa bu özetin üstüne kişiye özel tavsiyeler ekler; aksi hâlde fallback metni devreye girer.

## Kurulum Sayfası `install.php`
- Veritabanı yoksa oluşturur, `database.sql` demo verisini yükler.
- `CREATE DATABASE` veya `USE` komutları barındıran satırlar hosting kısıtına takılmasın diye otomatik atlanır.
- Kurulum tamamlandıktan sonra admin paneline dönüş butonu sunar.

## Geliştirme Notları
- Yeni soru eklerken `category_key` alanını doldurmayı unutmayın (örneğin `web_guvenligi`, `eposta_guvenligi`).
- Kişisel rapor çıktısını genişletmek için `SurveyService::groupAnswersByCategory()` fonksiyonunda kategori bazlı hesaplamalar yapılmaktadır.
- AI entegrasyonu kapalıysa `fallbackPersonalAdvice()` devreye girer.

Demo veri ile oynayıp kişisel raporu deneyin; yeni kategoriler oluşturup farklı alanlar için (ör. mobil uygulama güvenliği, fiziksel güvenlik) anında yeni bölümler ekleyebilirsiniz.
