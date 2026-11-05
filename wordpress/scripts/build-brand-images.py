#!/usr/bin/env python3
"""Pixel-preserving product presentation copies; original attachments stay untouched.

User explicitly selected Python/ImageMagick batch processing on 2026-11-05.
Only the reviewed 32 promotional corner marks are covered. Equipment/nameplates
are never reconstructed. All further operations are trim/resize/presentation.
"""
import argparse, hashlib, json
from pathlib import Path
from PIL import Image, ImageChops, ImageDraw, ImageFont, ImageStat

parser = argparse.ArgumentParser()
parser.add_argument('--inventory', type=Path, required=True)
parser.add_argument('--output', type=Path, required=True)
parser.add_argument('--font', type=Path, required=True)
args = parser.parse_args()
rows = json.loads(args.inventory.read_text())
args.output.mkdir(parents=True, exist_ok=True)
marked = {0,11,13,14,18,19,20,22,23,24,25,26,28,29,31,33,35,37,40,41,42,43,44,45,46,47,48,49,50,51,53,55}
font = lambda size: ImageFont.truetype(str(args.font),size)
results = []
for row in rows:
    source = Path(row['local'])
    assert hashlib.sha256(source.read_bytes()).hexdigest() == row['sha256']
    original = Image.open(source).convert('RGBA')
    rgb = Image.new('RGBA',original.size,'white'); rgb.alpha_composite(original)
    rgb = rgb.convert('RGB'); baseline = rgb.copy()
    mask = None
    if row['index'] in marked:
        assert rgb.size == (1000,1000)
        mask = (20,20,326,124)
        draw = ImageDraw.Draw(rgb)
        if row['index'] == 31:
            # An intentional brand badge on empty wall, not synthesized scenery.
            draw.rectangle(mask,fill='#0b0405')
            draw.text((34,58),'chinamudlogging.com',font=font(23),fill='white')
        else:
            draw.rectangle(mask,fill='white')
    difference = ImageChops.difference(rgb,baseline)
    if mask: ImageDraw.Draw(difference).rectangle(mask,fill='black')
    assert difference.getbbox() is None, 'Pixels outside the reviewed promotional mark changed'
    crop = (0,0,*rgb.size)
    # Trim only a uniformly white perimeter; never auto-cut photographic backgrounds.
    w,h = rgb.size
    edge_samples = [rgb.crop(b) for b in [(0,0,w,5),(0,h-5,w,h),(0,0,5,h),(w-5,0,w,h)]]
    white = all(min(ImageStat.Stat(im).mean)>247 for im in edge_samples)
    if white:
        diff = ImageChops.difference(rgb,Image.new('RGB',rgb.size,'white')).convert('L')
        box = diff.point(lambda v:255 if v>18 else 0).getbbox()
        if box and box[2]-box[0]>20 and box[3]-box[1]>20:
            pad = max(12,round(min(w,h)*.025))
            crop = (max(0,box[0]-pad),max(0,box[1]-pad),min(w,box[2]+pad),min(h,box[3]+pad))
    art = rgb.crop(crop)
    scale = min(1120/art.width,700/art.height,2)
    art = art.resize((max(1,round(art.width*scale)),max(1,round(art.height*scale))),Image.Resampling.LANCZOS)
    canvas = Image.new('RGB',(1200,900),'#0b0405'); draw=ImageDraw.Draw(canvas)
    draw.rectangle((8,66,1191,847),fill='#f7f7f7')
    draw.text((36,18),'chinamudlogging.com',font=font(30),fill='white')
    draw.line((36,64,1164,64),fill='#382e30',width=1)
    position = ((1200-art.width)//2,80+(740-art.height)//2)
    canvas.paste(art,position)
    placeholder = row['sha256']=='751b04edb90cc3bff48ff519d46de92553685cc74a3f7c0550e376a54ae4c580'
    if placeholder:
        draw.rectangle((8,66,1191,847),fill='#f7f7f7')
        for y,text,size in [(350,'PRODUCT IMAGE',32),(410,'Awaiting verified photograph',28)]:
            face=font(size); width=draw.textbbox((0,0),text,font=face)[2]
            draw.text(((1200-width)//2,y),text,font=face,fill='#534b4d')
    # Watermark occupies a reserved strip; no text covers hardware or a nameplate.
    domain='chinamudlogging.com'; label_font=font(21)
    tw=draw.textbbox((0,0),domain,font=label_font)[2]
    draw.text((1164-tw,815),domain,font=label_font,fill='#777173')
    draw.text((36,865),'EQUIPMENT / SENSORS / SYSTEM INTEGRATION',font=font(15),fill='#c4bfc0')
    name=row['sha256'][:24]
    full=args.output/(name+'.webp'); small=args.output/(name+'-800.webp')
    canvas.save(full,'WEBP',quality=90,method=6)
    canvas.resize((800,600),Image.Resampling.LANCZOS).save(small,'WEBP',quality=87,method=6)
    results.append({'index':row['index'],'attachment':row['id'],'source_sha256':row['sha256'],'file':full.name,'small':small.name,'sha256':hashlib.sha256(full.read_bytes()).hexdigest(),'small_sha256':hashlib.sha256(small.read_bytes()).hexdigest(),'corner_mark_removed':mask is not None,'placeholder':placeholder,'mask_box':mask,'trim_box':crop,'placement':[position[0],position[1],art.width,art.height],'source_dimensions':list(rgb.size),'outside_mark_pixel_changes':0,'bytes':full.stat().st_size,'small_bytes':small.stat().st_size})
(args.output/'manifest.json').write_text(json.dumps({'canvas':[1200,900],'watermark':'chinamudlogging.com','font_sha256':hashlib.sha256(args.font.read_bytes()).hexdigest(),'images':results},indent=2)+'\n')
print(json.dumps({'images':len(results),'reviewed_corner_marks':sum(r['corner_mark_removed'] for r in results),'bytes':sum(r['bytes']+r['small_bytes'] for r in results)}))
