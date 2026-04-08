import { Injectable, BadRequestException } from '@nestjs/common';
import axios from 'axios';

@Injectable()
export class AvatarService {
  private readonly baseUrl = 'https://api.dicebear.com/8.x';
  private readonly defaultStyle = 'adventurer';

  async getAvatarBase64(name: string): Promise<{ base64: string }> {
    if (!name) throw new BadRequestException('Missing name');

    const seed = encodeURIComponent(name);
    const url = `${this.baseUrl}/${this.defaultStyle}/svg?seed=${seed}`;

    const response = await axios.get(url, { responseType: 'text' });
    const svg = response.data;

    const base64 = Buffer.from(svg).toString('base64');
    return {
      base64: `data:image/svg+xml;base64,${base64}`,
    };
  }
}
