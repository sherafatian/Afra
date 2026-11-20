"""Build the two Word guides from adjacent Markdown; requires python-docx and Pillow.
Pass --fonts-dir with embeddable Vazir-Regular.ttf and Vazir-Bold.ttf.
"""
from pathlib import Path
from copy import deepcopy
import argparse,re,uuid,zipfile,json
from lxml import etree
from PIL import Image,ImageDraw,ImageFont
from docx import Document
from docx.shared import Cm,Pt,RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
parser=argparse.ArgumentParser();parser.add_argument('--fonts-dir',type=Path,required=True);args=parser.parse_args()
ROOT=Path(__file__).resolve().parent;REG=args.fonts_dir/'Vazir-Regular.ttf';BOLD=args.fonts_dir/'Vazir-Bold.ttf'
def prop(parent,name,value=None):
 e=parent.find(qn(name))
 if e is None:e=OxmlElement(name);parent.append(e)
 if value is not None:e.set(qn('w:val'),str(value))
 return e
def text(p,value,fa=False,bold=False,size=None):
 for part in re.split(r'([A-Za-z0-9][A-Za-z0-9_./:+@ -]*)',value.replace('**','')):
  if not part:continue
  r=p.add_run(part);pr=r._element.get_or_add_rPr();rf=prop(pr,'w:rFonts')
  for key in ['ascii','hAnsi','eastAsia']:rf.set(qn('w:'+key),'Cambria')
  rf.set(qn('w:cs'),'Vazir');prop(pr,'w:rtl',int(bool(re.search('[\u0600-\u06ff]',part))))
  if bold:prop(pr,'w:b',1);prop(pr,'w:bCs',1)
  if size:prop(pr,'w:sz',int(size*2));prop(pr,'w:szCs',int(size*2))
 if fa:p.alignment=WD_ALIGN_PARAGRAPH.RIGHT;prop(p._p.get_or_add_pPr(),'w:bidi',1);prop(p._p.get_or_add_pPr(),'w:jc','start')
def field(p,instruction,cache=''):
 for kind in ['begin','instruction','separate','value','end']:
  r=p.add_run()
  if kind=='instruction':e=OxmlElement('w:instrText');e.set(qn('xml:space'),'preserve');e.text=' '+instruction+' '
  elif kind=='value':e=OxmlElement('w:t');e.text=cache
  else:e=OxmlElement('w:fldChar');e.set(qn('w:fldCharType'),kind)
  r._r.append(e)
def embed(path):
 with zipfile.ZipFile(path) as z:parts={n:z.read(n) for n in z.namelist()}
 W='http://schemas.openxmlformats.org/wordprocessingml/2006/main';R='http://schemas.openxmlformats.org/officeDocument/2006/relationships';P='http://schemas.openxmlformats.org/package/2006/relationships'
 ft=etree.fromstring(parts['word/fontTable.xml']);font=next((x for x in ft if x.get('{'+W+'}name')=='Vazir'),None)
 if font is None:font=etree.SubElement(ft,'{'+W+'}font');font.set('{'+W+'}name','Vazir')
 rels=etree.Element('{'+P+'}Relationships',nsmap={None:P})
 for i,(kind,file) in enumerate([('embedRegular',REG),('embedBold',BOLD)]):
  key=uuid.uuid4();data=bytearray(file.read_bytes());secret=key.bytes[::-1]
  for j in range(32):data[j]^=secret[j%16]
  filename=f'Vazir-{i}.odttf';parts['word/fonts/'+filename]=bytes(data);node=etree.SubElement(font,'{'+W+'}'+kind);node.set('{'+R+'}id',f'rIdAfraFont{i}');node.set('{'+W+'}fontKey','{'+str(key).upper()+'}');node.set('{'+W+'}subsetted','0');rel=etree.SubElement(rels,'{'+P+'}Relationship');rel.set('Id',f'rIdAfraFont{i}');rel.set('Type',R+'/font');rel.set('Target','fonts/'+filename)
 parts['word/fontTable.xml']=etree.tostring(ft);parts['word/_rels/fontTable.xml.rels']=etree.tostring(rels)
 ct=etree.fromstring(parts['[Content_Types].xml']);node=etree.SubElement(ct,'{http://schemas.openxmlformats.org/package/2006/content-types}Default');node.set('Extension','odttf');node.set('ContentType','application/vnd.openxmlformats-officedocument.obfuscatedFont');parts['[Content_Types].xml']=etree.tostring(ct)
 with zipfile.ZipFile(path,'w',zipfile.ZIP_DEFLATED) as z:
  for key,value in parts.items():z.writestr(key,value)
for lang in ['fa','en']:
 fa=lang=='fa';d=Document();s=d.sections[0];s.page_width=Cm(21);s.page_height=Cm(29.7);s.top_margin=Cm(2.1);s.bottom_margin=Cm(2);s.left_margin=s.right_margin=Cm(1.8);s.different_first_page_header_footer=True
 for name,size in [('Normal',11.5),('Heading 1',22),('Heading 2',15),('Title',38),('Caption',9)]:
  style=d.styles[name];style.font.name='Cambria';style.font.size=Pt(size);pr=style.element.get_or_add_rPr();rf=prop(pr,'w:rFonts');rf.set(qn('w:cs'),'Vazir');prop(pr,'w:szCs',int(size*2));style.paragraph_format.line_spacing=1.4;style.paragraph_format.space_after=Pt(8)
  if name.startswith('Heading'):style.font.color.rgb=RGBColor.from_string('D65E0A');style.paragraph_format.keep_with_next=True
 header=s.header.paragraphs[0];text(header,'افرا | راهنمای کاربردی' if fa else 'AFRA | USER GUIDE',fa,True,10);bd=prop(header._p.get_or_add_pPr(),'w:pBdr');rule=prop(bd,'w:bottom','single');rule.set(qn('w:color'),'FA791B');rule.set(qn('w:sz'),'12')
 footer=s.footer.paragraphs[0];text(footer,'افرا • ویرایش ۵٫۱      صفحه ' if fa else 'Afra • Edition 5.1      Page ',fa,size=9);field(footer,'PAGE','1');text(footer,' / ',fa,size=9);field(footer,'NUMPAGES','1')
 # Branded full-page cover, generated locally from bundled logo and font.
 cover=Image.new('RGB',(1240,1754),'#fff8ef');paint=ImageDraw.Draw(cover)
 for yy in range(1754):
  blend=yy/1754;paint.line((0,yy,1240,yy),fill=tuple(round(a+(b-a)*blend)for a,b in zip((255,250,243),(255,237,216))))
 paint.rectangle((0,0,72,1754),fill='#fa791b');paint.polygon([(0,1430),(1240,1570),(1240,1754),(0,1754)],fill='#1b2330');paint.line((0,1420,1240,1560),fill='#fa791b',width=10)
 logo=Image.open(ROOT/'logo.png').convert('RGBA').resize((250,250));cover.paste(logo,(850 if fa else 160,150),logo)
 def cover_text(y,value,size,color='#243247',bold=False,x=None):
  font=ImageFont.truetype(str(BOLD if bold else REG),size)
  paint.text((x if x is not None else (1110 if fa else 160),y),value,font=font,fill=color,anchor='ra'if fa else 'la',direction='rtl'if fa else 'ltr')
 cover_text(470,'افرا'if fa else 'Afra',125,'#d15a08',True)
 cover_text(665,'راهنمای یادگیری و کار با سامانه'if fa else 'Learning & user guide',43,bold=True)
 cover_text(775,'از نیازمندی روشن تا نتیجه قابل اعتماد'if fa else 'From requirements to trusted results',30,'#786854')
 paint.rounded_rectangle((160,920,1110,1190),24,fill='#fffdf8',outline='#f0d5b8',width=2)
 cover_text(950,'۱  اصول تضمین کیفیت و طراحی تست کیس'if fa else '01   QA principles & test design',28,bold=True,x=1060 if fa else 200)
 cover_text(1065,'۲  راهنمای گام‌به‌گام سامانه'if fa else '02   Afra, step by step',28,bold=True,x=1060 if fa else 200)
 cover_text(1595,'ویرایش ۵٫۱ • ۲۹ آبان ۱۴۰۵'if fa else 'Edition 5.1 • 20 November 2026',27,'#ffad64')
 cover_text(1670,'کیفیت، ریشه در دقت دارد.'if fa else 'Quality starts with care.',30,'#ffffff')
 cover_path=ROOT/f'cover-{lang}.png';cover.save(cover_path,optimize=True)
 paragraph=d.add_paragraph();inline=paragraph.add_run().add_picture(str(cover_path),width=Cm(21),height=Cm(29.7))._inline
 anchor=OxmlElement('wp:anchor')
 for key,value in {'distT':'0','distB':'0','distL':'0','distR':'0','simplePos':'0','relativeHeight':'0','behindDoc':'1','locked':'1','layoutInCell':'1','allowOverlap':'1'}.items():anchor.set(key,value)
 simple=OxmlElement('wp:simplePos');simple.set('x','0');simple.set('y','0');anchor.append(simple)
 for axis in ['H','V']:
  position=OxmlElement('wp:position'+axis);position.set('relativeFrom','page');offset=OxmlElement('wp:posOffset');offset.text='0';position.append(offset);anchor.append(position)
 anchor.append(deepcopy(inline.find(qn('wp:extent'))));anchor.append(OxmlElement('wp:wrapNone'))
 for tag in ['wp:docPr','wp:cNvGraphicFramePr','a:graphic']:anchor.append(deepcopy(inline.find(qn(tag))))
 inline.getparent().replace(inline,anchor)
 d.add_page_break();p=d.add_paragraph(style='Heading 1');text(p,'فهرست مطالب' if fa else 'Contents',fa)
 p=d.add_paragraph();text(p,'در Word روی فهرست کلیک راست کنید و Update Field سپس Update entire table را بزنید. شماره صفحه‌ها با خود Word محاسبه می‌شوند.' if fa else 'In Word, right-click this table and choose Update Field, then Update entire table. Word calculates the page numbers.',fa,size=10)
 source=(ROOT/f'user-guide.{lang}.md').read_text().splitlines();heads=[(min(2,len(x)-len(x.lstrip('#'))),x.lstrip('# '))for x in source if x.startswith(('# Chapter','# فصل','## '))]
 p=d.add_paragraph()
 for kind in ['begin','instruction','separate']:
  run=p.add_run()
  if kind=='instruction':element=OxmlElement('w:instrText');element.set(qn('xml:space'),'preserve');element.text=' TOC \\o "1-2" \\h \\z \\u '
  else:element=OxmlElement('w:fldChar');element.set(qn('w:fldCharType'),kind)
  run._r.append(element)
 page_file=ROOT/f'pages-{lang}.json';pages=json.loads(page_file.read_text())if page_file.exists()else{}
 for idx,(level,title)in enumerate(heads):
  p=d.add_paragraph();p.paragraph_format.space_after=Pt(4);p.paragraph_format.line_spacing=1.2
  text(p,title,fa,bold=level==1,size=12 if level==1 else 10)
  p.add_run('  ···  ');field(p,'PAGEREF AfraHeading'+str(idx)+' \\h',str(pages.get(title,'—')))
 p=d.add_paragraph();run=p.add_run();element=OxmlElement('w:fldChar');element.set(qn('w:fldCharType'),'end');run._r.append(element)
 d.add_page_break();heading_index=0
 def bookmark(paragraph):
  global heading_index
  first=OxmlElement('w:bookmarkStart');first.set(qn('w:id'),str(heading_index+100));first.set(qn('w:name'),'AfraHeading'+str(heading_index));paragraph._p.insert(0,first)
  last=OxmlElement('w:bookmarkEnd');last.set(qn('w:id'),str(heading_index+100));paragraph._p.append(last);heading_index+=1

 for line in source:
  line=line.strip()
  if not line:continue
  if line.startswith('# '):
   if line.startswith(('# Chapter','# فصل')):d.add_page_break();p=d.add_paragraph(style='Heading 1');text(p,line[2:],fa);bookmark(p)
   continue
  if line.startswith('## '):p=d.add_paragraph(style='Heading 2');text(p,line[3:],fa);bookmark(p);continue
  image=re.match(r'!\[(.*?)\]\((.*?)\)',line)
  if image:
   path=(ROOT/image[2]).resolve()
   if path.exists():
    p=d.add_paragraph();p.alignment=WD_ALIGN_PARAGRAPH.CENTER;p.paragraph_format.keep_with_next=True;image_width,image_height=Image.open(path).size;p.add_run().add_picture(str(path),width=Cm(min(17,22*image_width/image_height)));p=d.add_paragraph(style='Caption');text(p,image[1],fa)
   continue
  p=d.add_paragraph(style='List Bullet' if line.startswith('* ') or line.startswith('- ') else 'Normal');text(p,line[2:] if line.startswith(('* ','- '))else line,fa)
 prop(d.settings.element,'w:updateFields','true');prop(d.settings.element,'w:embedTrueTypeFonts','true');d.core_properties.title='راهنمای افرا' if fa else 'Afra user guide';d.core_properties.author='Afra';d.core_properties.language='fa-IR' if fa else 'en-US'
 target=ROOT/f'afra-user-guide-{lang}.docx';d.save(target);embed(target);print(target.name,target.stat().st_size)
