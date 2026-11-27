from pathlib import Path
import tempfile
import fitz
from reportlab.pdfgen import canvas
from reportlab.lib.colors import HexColor
from reportlab.lib.pagesizes import A4
from reportlab.lib.utils import ImageReader

root=Path(__file__).resolve().parents[1]
src=root/'public/assets/tennilo-clubdisplay-produktdatenblatt-basic-plus.pdf'
logo=root/'public/assets/clubdisplay.png'
W,H=A4
navy='#102b40'; teal='#087d83'; dark='#142d3e'; muted='#466070'
def price_page(out):
    c=canvas.Canvas(str(out),pagesize=A4)
    def text(x,y,t,size=10,bold=False,color=dark):
        c.setFillColor(HexColor(color));c.setFont('Helvetica-Bold' if bold else 'Helvetica',size);c.drawString(x,y,t)
    c.setFillColor(HexColor(navy));c.rect(0,H-122,W,122,fill=1,stroke=0)
    if logo.exists():
        im=ImageReader(str(logo));iw,ih=im.getSize();w=250;h=w*ih/iw
        c.drawImage(im,36,H-42-h,width=w,height=h,mask='auto')
    text(36,H-104,'PREISBLATT / 27.11.2026',10,True,'#a5e5e5')
    text(36,H-159,'Ein Verein. Drei Zahlungsmodelle.',22,True)
    text(36,H-182,'Unverbindliche Softwarepreise je Verein / Standort, inklusive eines Displays.',10)
    y=H-215
    rows=[('Lizenz & Leistung','BASIC','PLUS'),('Monatsabo','19 €','39 €'),('Jahresabo','190 €','390 €'),('Einrichtung beim Abo (einmalig)','149 €','299 €'),('Kauflizenz (einmalig)','699 €','1.399 €'),('Einrichtung beim Kauf','Inklusive','Inklusive'),('Updates & Support beim Kauf','24 Monate','24 Monate'),('Verlängerung: 12 Monate','99 €','199 €'),('Verlängerung: 24 Monate','179 €','359 €'),('Zusatzdisplay (monatlich / jährlich)','9 € / 90 €','9 € / 90 €'),('Zusatzdisplay (einmalig)','249 €','249 €')]
    for i,(label,basic,plus) in enumerate(rows):
        c.setFillColor(HexColor(navy if i==0 else '#e4f3f2' if i==4 else '#f1f5f7' if i%2 else '#ffffff'));c.rect(36,y-25,W-72,25,fill=1,stroke=0)
        color='#ffffff' if i==0 else dark
        text(45,y-16,label,10,i in (0,4),color);text(335,y-16,basic,10,i in (0,4),color);text(455,y-16,plus,10,i in (0,4),color);y-=25
    y-=25
    sections=[
      ('Abonnement','Updates und regulärer technischer Support während aktiver Laufzeit inklusive.','Einrichtung wird einmalig berechnet.'),
      ('Kauflizenz','Einrichtung und 24 Monate Updates / Support inklusive. Danach unbefristete','Nutzung des erworbenen Softwarestands; Verlängerung freiwillig.'),
      ('Hosting und Voraussetzungen','Vereinseigenes Hosting ist Standard; betreutes Hosting separat nach Angebot.','Hardware, Fremdlizenzen, individuelle Schnittstellen und Vor-Ort-Service separat.')]
    for title,line1,line2 in sections:
        text(36,y,title,12,True,teal);text(36,y-17,line1,10);text(36,y-31,line2,10);y-=57
    text(36,y,'Ggf. zzgl. Umsatzsteuer. Sicherheitsupdates werden gesondert geregelt.',9,False,muted)
    text(36,y-13,'Verbindlicher Umfang und Preise nach Pilotphase und schriftlichem Angebot.',9,False,muted)
    text(36,36,'TENNILO ClubDisplay  /  Ergänzung zum Produktdatenblatt',9,True,teal)
    text(438,36,'tennilo.de/clubdisplay/',9,False,muted)
    c.save()

with tempfile.TemporaryDirectory() as d:
    annex=Path(d)/'price.pdf';price_page(annex)
    with fitz.open(src) as old,fitz.open(annex) as add:
        assert len(old)==6, 'Expected original six-page datasheet'
        assert len(add)==1
        check=add[0].get_text()
        for token in ('699 €','1.399 €','24 Monate','179 €','359 €','249 €'):
            assert token in check, token
        old.insert_pdf(add)
        target=Path(d)/'complete.pdf';old.save(target,garbage=4,deflate=True)
    src.write_bytes(target.read_bytes())
with fitz.open(src) as final:
    assert len(final)==7
    assert '1.399 €' in final[-1].get_text()
print('ClubDisplay datasheet: original six pages + updated pricing annex')
