import { Injectable, OnModuleInit } from "@nestjs/common";
import { InjectModel } from "@nestjs/mongoose";
import { Contact } from "../contact/contact.schema";
import { Post } from "../posts/post.schema";
import { Model } from "mongoose";

@Injectable()
export class IndexSyncService implements OnModuleInit {
  constructor(
    @InjectModel(Contact.name) private contactModel: Model<Contact>,
    @InjectModel(Post.name) private postModel: Model<Post>,
    // Thêm các model khác nếu cần
  ) {}

  async onModuleInit() {
    if (process.env.SYNC_INDEX_MONGO === 'true') {
      console.log('[IndexSync] Syncing indexes...');
      await Promise.all([
        this.contactModel.syncIndexes(),
        this.postModel.syncIndexes(),
        // ... thêm model ở đây
      ]);
      console.log('[IndexSync] Index sync complete');
    }
  }
}