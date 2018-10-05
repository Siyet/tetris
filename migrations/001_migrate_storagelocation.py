# -= О П Ц И И =-
from config import API_URL_BASE, HEADERS
# -=-=-=-=-=-=-=-
# -= Принятые сокращения =-
# sloc - storagelocation
# prod - product
# -=-=-=-=-=-=-=-=-=-=-=-=-
import requests
import json
import csv
import time
import os
import re
from datetime import datetime

def _now_str():
    return datetime.strftime(datetime.now(), "%Y-%m-%d %H:%M:%S")

print('"[{0}] Start migration script <{1}>"'.format(_now_str(), os.path.basename(__file__)))
goods = []
response = requests.get('{0}bulk/exports/?type=items&mode=include&fields=sku,storagelocation'.format(API_URL_BASE), headers=HEADERS)
if response.status_code == 200:
    data = json.loads(response.content.decode('utf-8'))
    if data['result'] != 'success':
        print(data['message'])
        raise
    time.sleep(8)
    _response = requests.get('{0}bulk/exports/{1}'.format(API_URL_BASE, data['export_file']), headers=HEADERS)
    if _response.status_code == 200:
        _data = json.loads(_response.content.decode('utf-8'))
        __response = requests.get(_data['url'])
        if __response.status_code == 200:
            _csv = csv.DictReader(__response.content.decode('utf-8').split('\n')[:-1], delimiter=',', quotechar='"')
            for row in _csv:
                goods.append(row)
print('"[{0}] Recieved {1} goods"'.format(_now_str(), len(goods)))
goods2fix = []
warn_sloc_count = 0
print('"Goods with unexpected data in storagelocation:";;')
print('"SKU";"storagelocation (before)";"storagelocation (after)"')
for prod in goods:
    if prod["storagelocation"] == "":
        continue
    sloc_parts = re.split('[\s,]+', prod["storagelocation"])
    logging = False
    migrate = False
    for idx, sloc_part in enumerate(sloc_parts):
        if re.match('[0-9]{5}(/[0-9]{1,2})?$', sloc_part):
            sloc_parts[idx] = '0' + sloc_part
            migrate = True
        elif not re.match('[0-9]{6}(/[0-9]{1,2})?$', sloc_part):
            logging = True
            warn_sloc_count += 1
    if logging:
        print('"{0}";"{1}";"{2}"'.format(prod["sku"], prod["storagelocation"], " ".join(sloc_parts)))
    if migrate:
        prod["storagelocation"] = " ".join(sloc_parts)
        goods2fix.append(prod)

print('"[{0}] Migration script finished:"'.format(_now_str()))
print('" - goods updated total: {0}"'.format(len(goods2fix)))
print('" - goods with unexpected data in storagelocation total: {0}"'.format(warn_sloc_count))
i = 0
for prod in goods2fix:
    data = {
        'identifier': 'sku',
        'sku': prod['sku'],
        'storagelocation': prod['storagelocation']
    }
    _response = requests.post('{0}editor/items/edit'.format(API_URL_BASE), headers=HEADERS, data=data)
    if _response.status_code != 200:
        time.sleep(16)
        __response = requests.post('{0}editor/items/edit'.format(API_URL_BASE), headers=HEADERS, data=data)
        if __response.status_code != 200:
            __response.raise_for_status()
    i += 1
    if i%100 == 0:
        print('"[{0}] {1} goods updated of {2}"'.format(_now_str(), i, len(goods2fix)))