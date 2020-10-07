Args<-commandArgs()
in_file<-Args[6]
normal_list<-as.numeric(unlist(strsplit(Args[7],split=',')))
gene_stat_file<-Args[8]
loading_file<-Args[9]
coord_file<-Args[10]
std_file<-Args[11]

data<-read.table(in_file, header=T, com='', sep="\t")
d<-data[,2:length(data)]
mat<-as.matrix(d) # convert to matrix
mat<-t(mat)
colnames(mat)<-data[,1]

normal_mat<-mat[c(normal_list),]

sd_gene=apply(mat, 2, sd)
mean_gene=apply(mat, 2, mean)
median_gene=apply(mat, 2, median)
sd_normal=apply(normal_mat, 2, sd)
mean_normal=apply(normal_mat, 2, mean)
median_normal=apply(normal_mat, 2, median)
gene_stat=cbind(as.matrix(mean_gene),as.matrix(sd_gene),as.matrix(median_gene),as.matrix(mean_normal),as.matrix(sd_normal),as.matrix(median_normal))
write.table(gene_stat, file=gene_stat_file, sep='\t', col.names=FALSE);

if (!is.na(loading_file)) {
	res<-prcomp(mat)
	write.table(res$rotation[,1:20], file=loading_file, sep='\t', col.names=FALSE);
	write.table(res$x[,1:3], file=coord_file, sep='\t', col.names=FALSE);
	write.table(res$sdev, file=std_file, sep='\t', col.names=FALSE);
}

