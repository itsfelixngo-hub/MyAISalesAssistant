import { Injectable, Logger } from '@nestjs/common';
import { Cron, CronExpression } from '@nestjs/schedule';
import { InjectModel } from '@nestjs/mongoose';
import { Model } from 'mongoose';
import { Post } from 'src/modules/posts/post.schema';

@Injectable()
export class PostSchedulerService {
  private readonly logger = new Logger(PostSchedulerService.name);

  constructor(
    @InjectModel(Post.name) private postModel: Model<Post>,
  ) {}

  @Cron(CronExpression.EVERY_MINUTE)
  async handleScheduledPosts() {
    const now = new Date();

    const posts = await this.postModel.find({
      status: 'scheduled',
      scheduledAt: { $lte: now },
    });

    if (posts.length > 0) {
      this.logger.log(`Found ${posts.length} scheduled post(s) to publish`);

      const ids = posts.map(p => p._id);
      await this.postModel.updateMany(
        { _id: { $in: ids } },
        {
          $set: {
            status: 'posted',
            publishedAt: now,
          },
        },
      );
    }
  }
}
