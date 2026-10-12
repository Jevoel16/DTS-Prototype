import csv
from datetime import datetime

input_file = '/home/mirkolouis/Documents/DTS Prototype/from_dts/dts_docroutes (1).csv'
output_file_1 = '/home/mirkolouis/Documents/DTS Prototype/from_dts/week_2026_07_20_to_07_23.csv'
output_file_2 = '/home/mirkolouis/Documents/DTS Prototype/from_dts/week_2026_07_27_to_07_30.csv'

start_1 = datetime(2026, 7, 20)
end_1 = datetime(2026, 7, 23, 23, 59, 59)

start_2 = datetime(2026, 7, 27)
end_2 = datetime(2026, 7, 30, 23, 59, 59)

with open(input_file, 'r', encoding='utf-8') as f_in, \
     open(output_file_1, 'w', encoding='utf-8', newline='') as f_out_1, \
     open(output_file_2, 'w', encoding='utf-8', newline='') as f_out_2:
    
    reader = csv.reader(f_in)
    writer_1 = csv.writer(f_out_1, quoting=csv.QUOTE_ALL)
    writer_2 = csv.writer(f_out_2, quoting=csv.QUOTE_ALL)
    
    header = next(reader)
    writer_1.writerow(header)
    writer_2.writerow(header)
    
    date_col_idx = header.index("datetime_forwarded")
    
    count_1 = 0
    count_2 = 0
    
    for row in reader:
        date_str = row[date_col_idx]
        if date_str and date_str != '0000-00-00 00:00:00':
            try:
                # Format: "2022-05-02 09:18:26"
                dt = datetime.strptime(date_str, "%Y-%m-%d %H:%M:%S")
                
                if start_1 <= dt <= end_1:
                    writer_1.writerow(row)
                    count_1 += 1
                elif start_2 <= dt <= end_2:
                    writer_2.writerow(row)
                    count_2 += 1
            except ValueError:
                pass
                
print(f"Extraction complete.")
print(f"Week 1 (July 20-23): {count_1} records")
print(f"Week 2 (July 27-30): {count_2} records")
