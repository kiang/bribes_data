# -*- coding: utf-8 -*-
import csv, json, os
os.environ["CUDA_DEVICE_ORDER"]="PCI_BUS_ID" # see issue #152
os.environ["CUDA_VISIBLE_DEVICES"]="0"
from ckiptagger import WS, POS, NER
class SetEncoder(json.JSONEncoder):
    def default(self, obj):
        if isinstance(obj, set):
            return list(obj)
        return json.JSONEncoder.default(self, obj)

toSkip = [
    '200610/臺灣高等法院臺中分院刑事/TCHM,95,選上訴,1051,20061025,1.json',
    '200701/臺灣高等法院高雄分院民事/KSHV,95,選上,5,20070117,1.json',
    '200807/臺灣高等法院臺中分院刑事/TCHM,97,選上更(二),126,20080703,1.json'
]

pos = POS("ckip/data")
ner = NER("ckip/data")
with open('targets.csv') as csvFile:
    rows = csv.reader(csvFile)
    next(rows)
    for row in rows:
        with open('filter/' + row[0]) as jsonFile:
            if row[0] in toSkip:
                continue
            targetFile = 'meta/' + row[0]
            if os.path.exists(targetFile):
                continue
            print("processing " + row[0])
            dirname = os.path.dirname(targetFile)
            if False == os.path.exists(dirname):
                os.makedirs(dirname, 0o777)
            data = json.load(jsonFile)

            pos_results = pos([data['JFULL']])
            ner_results = ner([data['JFULL']], pos_results)

            with open(targetFile, 'w', encoding='utf-8') as f:
                json.dump(ner_results, f, cls=SetEncoder)

